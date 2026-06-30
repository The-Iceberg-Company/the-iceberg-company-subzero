<?php



declare(strict_types=1);



namespace Iceberg\Subzero\Models;



final class RevealCallerContext

{

    public function __construct(

        public readonly ?string $file = null,

        public readonly ?int $line = null,

        public readonly ?string $function = null,

        public readonly ?string $fieldRef = null,

        public readonly ?string $routeRef = null,

        public readonly ?string $pagePathHash = null,

        public readonly ?string $elementRefHash = null,

        public readonly ?string $sdk = null,

        public readonly ?string $sdkVersion = null,

        public readonly ?string $teamRef = null,

        public readonly ?string $appRef = null,

        public readonly ?string $serviceRef = null,

        public readonly ?string $repoRef = null,

        public readonly ?string $branchRef = null,

        public readonly ?string $commitHash = null,

        public readonly ?string $buildId = null,

        public readonly ?string $deployRef = null,

        public readonly ?string $envRef = null,

    ) {

    }



    /** @return array<string, mixed> */

    public function toPayload(): array

    {

        $payload = array_filter([

            'file' => $this->file,

            'line' => $this->line,

            'function' => $this->function,

            'field_ref' => $this->fieldRef,

            'route_ref' => $this->routeRef,

            'page_path_hash' => $this->pagePathHash,

            'element_ref_hash' => $this->elementRefHash,

            'sdk' => $this->sdk,

            'sdk_version' => $this->sdkVersion,

            'team_ref' => $this->teamRef,

            'app_ref' => $this->appRef,

            'service_ref' => $this->serviceRef,

            'repo_ref' => $this->repoRef,

            'branch_ref' => $this->branchRef,

            'commit_hash' => $this->commitHash,

            'build_id' => $this->buildId,

            'deploy_ref' => $this->deployRef,

            'env_ref' => $this->envRef,

        ], static fn ($value) => $value !== null);



        if ($payload === []) {

            throw new \InvalidArgumentException('caller_context must include at least one field');

        }



        return $payload;

    }

}



final class RevealGrantResult

{

    public function __construct(

        public readonly string $grantId,

        public readonly string $expiresAt,

    ) {

    }



}


