<?php



declare(strict_types=1);



namespace Iceberg\Subzero\Internal;



use Iceberg\Subzero\Models\RevealCallerContext;



final class CallerContextCapture

{

    private const SDK_VERSION = '0.3.2';



    /** @var array<string, string> */

    private const ENV_FIELD_MAP = [

        'SUBZERO_TEAM_REF' => 'teamRef',

        'SUBZERO_APP_REF' => 'appRef',

        'SUBZERO_SERVICE_REF' => 'serviceRef',

        'SUBZERO_REPO_REF' => 'repoRef',

        'SUBZERO_BRANCH_REF' => 'branchRef',

        'SUBZERO_COMMIT_SHA' => 'commitHash',

        'SUBZERO_BUILD_ID' => 'buildId',

        'SUBZERO_DEPLOY_REF' => 'deployRef',

        'SUBZERO_ENV_REF' => 'envRef',

    ];



    public static function captureServerCallerContext(): RevealCallerContext

    {

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach ($trace as $frame) {

            $file = isset($frame['file']) ? str_replace('\\', '/', (string) $frame['file']) : null;

            if ($file === null) {

                continue;

            }

            if (str_contains($file, '/Iceberg/Subzero/') || str_contains($file, '\\Iceberg\\Subzero\\')) {

                continue;

            }

            if (str_ends_with($file, 'CallerContextCapture.php')) {

                continue;

            }



            return new RevealCallerContext(

                file: $file,

                line: isset($frame['line']) ? (int) $frame['line'] : null,

                function: isset($frame['function']) ? (string) $frame['function'] : null,

                sdk: 'php',

                sdkVersion: self::SDK_VERSION,

            );

        }



        return new RevealCallerContext(sdk: 'php', sdkVersion: self::SDK_VERSION);

    }



    /** @return array<string, string> */

    public static function captureDeploymentContext(): array

    {

        $payload = [];

        foreach (self::ENV_FIELD_MAP as $envName => $fieldName) {

            $value = getenv($envName);

            if (is_string($value) && $value !== '') {

                $payload[$fieldName] = trim($value);

            }

        }



        $commit = self::firstEnv(['SUBZERO_COMMIT_SHA', 'GITHUB_SHA', 'CI_COMMIT_SHA']);

        if ($commit !== null && !isset($payload['commitHash'])) {

            $normalized = self::normalizeCommitHash($commit);

            if ($normalized !== null) {

                $payload['commitHash'] = $normalized;

            }

        }



        $buildId = self::firstEnv(['SUBZERO_BUILD_ID', 'GITHUB_RUN_ID', 'CI_PIPELINE_ID']);

        if ($buildId !== null && !isset($payload['buildId'])) {

            $payload['buildId'] = $buildId;

        }



        return $payload;

    }



    public static function resolveRevealCallerContext(

        ?RevealCallerContext $manual,

        bool $capture,

        ?RevealCallerContext $deploymentContext = null,

        bool $captureDeployment = true,

    ): ?array {

        $parts = [];



        if ($capture) {

            $parts[] = self::captureServerCallerContext()->toPayload();

        }

        if ($captureDeployment) {

            $parts[] = self::payloadFromDeployment(self::captureDeploymentContext());

        }

        if ($deploymentContext !== null) {

            $parts[] = $deploymentContext->toPayload();

        }

        if ($manual !== null) {

            $parts[] = $manual->toPayload();

        }



        if ($parts === []) {

            return null;

        }



        $merged = array_merge(...$parts);

        if ($merged === []) {

            return null;

        }



        return $merged;

    }



    /** @param array<string, string> $values */

    private static function payloadFromDeployment(array $values): array

    {

        $payload = [];

        if (isset($values['teamRef'])) {

            $payload['team_ref'] = $values['teamRef'];

        }

        if (isset($values['appRef'])) {

            $payload['app_ref'] = $values['appRef'];

        }

        if (isset($values['serviceRef'])) {

            $payload['service_ref'] = $values['serviceRef'];

        }

        if (isset($values['repoRef'])) {

            $payload['repo_ref'] = $values['repoRef'];

        }

        if (isset($values['branchRef'])) {

            $payload['branch_ref'] = $values['branchRef'];

        }

        if (isset($values['commitHash'])) {

            $payload['commit_hash'] = $values['commitHash'];

        }

        if (isset($values['buildId'])) {

            $payload['build_id'] = $values['buildId'];

        }

        if (isset($values['deployRef'])) {

            $payload['deploy_ref'] = $values['deployRef'];

        }

        if (isset($values['envRef'])) {

            $payload['env_ref'] = $values['envRef'];

        }



        return $payload;

    }



    /** @param list<string> $names */

    private static function firstEnv(array $names): ?string

    {

        foreach ($names as $name) {

            $value = getenv($name);

            if (is_string($value) && $value !== '') {

                return trim($value);

            }

        }



        return null;

    }



    private static function normalizeCommitHash(string $value): ?string

    {

        $normalized = strtolower(trim($value));

        if (str_starts_with($normalized, 'sha256:')) {

            $normalized = substr($normalized, 7);

        }

        if (strlen($normalized) > 40) {

            $normalized = substr($normalized, 0, 40);

        }

        if (!preg_match('/^[a-f0-9]{7,40}$/', $normalized)) {

            return null;

        }



        return $normalized;

    }

}


