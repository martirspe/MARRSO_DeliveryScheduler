<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Adminhtml;

/**
 * Normalizes POST payloads from Magento UI forms.
 */
class FormDataExtractor
{
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

        if (isset($data['general']) && is_array($data['general'])) {
            $data = array_replace($data, $data['general']);
            unset($data['general']);
        }

        foreach ($post as $key => $value) {
            if (!is_array($value) && !in_array($key, ['form_key', 'key'], true)) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
