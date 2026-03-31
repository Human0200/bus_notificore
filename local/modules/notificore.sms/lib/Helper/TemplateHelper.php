<?php

namespace Notificore\Sms\Helper;

final class TemplateHelper
{
    public static function render(string $template, array $context): string
    {
        $template = trim($template);

        if ($template === '') {
            return '';
        }

        $replacements = [];

        foreach ($context as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $normalizedValue = trim((string)$value);
            $normalizedKey = trim((string)$key);

            if ($normalizedKey === '') {
                continue;
            }

            foreach (array_unique([
                $normalizedKey,
                mb_strtolower($normalizedKey),
                mb_strtoupper($normalizedKey),
            ]) as $variant) {
                $replacements['{' . $variant . '}'] = $normalizedValue;
            }
        }

        return trim(strtr($template, $replacements));
    }
}
