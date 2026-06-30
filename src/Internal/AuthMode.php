<?php

declare(strict_types=1);

namespace Iceberg\Subzero\Internal;

enum AuthMode: string
{
    case None = 'none';
    case TokenizeKey = 'tokenize_key';
    case RevealKey = 'reveal_key';
    case RevealGrantKey = 'reveal_grant_key';
    case ProxyKey = 'proxy_key';
}
