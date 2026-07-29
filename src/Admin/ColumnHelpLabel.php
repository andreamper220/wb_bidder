<?php

namespace App\Admin;

final class ColumnHelpLabel
{
    public static function make(string $label, string $help): string
    {
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedHelp = htmlspecialchars($help, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(
            '%s <i class="fa fa-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="%s" style="cursor:help;font-size:0.85em;" tabindex="0" aria-label="Пояснение"></i>',
            $escapedLabel,
            $escapedHelp,
        );
    }
}
