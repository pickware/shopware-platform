<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ColorPickerField extends CustomFieldType
{
    protected function toEntityArray(): array
    {
        return [
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'type' => 'colorpicker',
                'componentName' => 'sw-field',
                'customFieldType' => 'colorpicker',
            ],
        ];
    }
}
