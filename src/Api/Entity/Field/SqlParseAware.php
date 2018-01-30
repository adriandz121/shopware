<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Field;

use Shopware\Context\Struct\TranslationContext;

interface SqlParseAware
{
    public function parse(string $selection, TranslationContext $context): string;
}
