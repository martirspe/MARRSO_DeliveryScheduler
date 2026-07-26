<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Adminhtml;

/**
 * Normalizes POST payloads from Magento UI forms.
 */
class FormDataExtractor
{
    private const ENTITY_FIELD_KEYS = [
        'entity_id',
        'name',
        'code',
        'address',
        'district',
        'latitude',
        'longitude',
        'priority',
        'is_active',
        'slot_date',
        'start_time',
        'end_time',
        'price',
        'capacity',
        'used_capacity',
        'carrier_code',
        'retention_days',
        'auto_generate_slots',
        'pickup_location_id',
        'holiday_date',
        'description',
    ];

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function extract(array $post): array
    {
        $data = $post;

        if (isset($post['data']) && is_array($post['data'])) {
            $data = $post['data'];
        }

        $data = self::flattenFormData($data);

        foreach ($post as $key => $value) {
            if (!is_array($value) && !in_array($key, ['form_key', 'key'], true)) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Flattens nested UI scopes: entity id -> fieldset (general) -> fields.
     *
     * @param array<string|int, mixed> $data
     * @return array<string, mixed>
     */
    private static function flattenFormData(array $data): array
    {
        for ($depth = 0; $depth < 6; $depth++) {
            if (self::containsEntityFields($data)) {
                return $data;
            }

            $data = self::unwrapNextScope($data);
        }

        return $data;
    }

    /**
     * @param array<string|int, mixed> $data
     */
    private static function unwrapNextScope(array $data): array
    {
        if (isset($data['']) && is_array($data[''])) {
            return $data[''];
        }

        if (count($data) === 1) {
            $key = array_key_first($data);
            $value = $data[$key];
            if (is_array($value) && ($key === '' || is_numeric($key))) {
                return $value;
            }
        }

        foreach ($data as $key => $value) {
            if (!is_string($key) || !is_array($value)) {
                continue;
            }

            if (self::containsEntityFields($value)) {
                return array_replace($data, $value);
            }
        }

        return $data;
    }

    /**
     * @param array<string|int, mixed> $data
     */
    private static function containsEntityFields(array $data): bool
    {
        foreach (self::ENTITY_FIELD_KEYS as $field) {
            if (array_key_exists($field, $data)) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeDate(mixed $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        foreach (['d/m/Y', 'j/n/Y', 'm/d/Y', 'n/j/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date === false) {
                continue;
            }

            $errors = \DateTimeImmutable::getLastErrors();
            if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : $value;
    }

    public static function normalizeTime(mixed $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)) {
            $parts = explode(':', $value);

            return sprintf('%02d:%02d:%02d', (int)$parts[0], (int)$parts[1], (int)($parts[2] ?? 0));
        }

        return $value;
    }
}
