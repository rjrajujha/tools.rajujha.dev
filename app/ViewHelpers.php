<?php
declare(strict_types=1);

function tool_icon(string $slug, string $sizeClass = 'size-5'): string
{
    return \App\Catalog::icon($slug, $sizeClass);
}
