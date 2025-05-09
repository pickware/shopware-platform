<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class ThemeConfigField extends Struct
{
    protected string $name;

    /**
     * @var array<string, array<string, string>>|null
     *
     * @deprecated tag:v6.8.0 - Property will be removed. Use translations via labelSnippetKey instead
     */
    protected ?array $label = null;

    protected string $labelSnippetKey;

    /**
     * @var array<string, array<string, string>>|null
     *
     * @deprecated tag:v6.8.0 - Property will be removed. Use translations via helpTextSnippetKey instead
     */
    protected ?array $helpText = null;

    protected string $helpTextSnippetKey;

    protected ?string $type;

    /**
     * @deprecated tag:v6.8.0 - Property will be typed natively as array|string
     *
     * @var list<string>|string
     *
     * @phpstan-ignore shopware.propertyNativeType (Will be natively typed with next major)
     */
    protected $value;

    protected ?bool $editable = null;

    protected ?string $block = null;

    protected ?string $section = null;

    protected ?string $tab = null;

    protected ?int $order = null;

    protected ?int $sectionOrder = null;

    protected ?int $blockOrder = null;

    protected ?int $tabOrder = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $custom = null;

    protected ?bool $scss = null;

    protected ?bool $fullWidth = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return array<string, array<string, string>>|null
     *
     * @deprecated tag:v6.8.0 - Property will be removed. Use translations via labelSnippetKey instead
     */
    public function getLabel(): ?array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0', 'getLabelSnippetKey'));

        return $this->label;
    }

    /**
     * @param array<string, array<string, string>>|null $label
     *
     * @deprecated tag:v6.8.0 - Property will be removed. Use translations via labelSnippetKey instead
     */
    public function setLabel(?array $label): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'));

        $this->label = $label;
    }

    public function getLabelSnippetKey(): string
    {
        return $this->labelSnippetKey;
    }

    public function setLabelSnippetKey(string $labelSnippetKey): void
    {
        $this->labelSnippetKey = $labelSnippetKey;
    }

    public function getHelpTextSnippetKey(): string
    {
        return $this->helpTextSnippetKey;
    }

    public function setHelpTextSnippetKey(string $helpTextSnippetKey): void
    {
        $this->helpTextSnippetKey = $helpTextSnippetKey;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Parameter will be natively typed
     *
     * @return list<string>|string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-name-change - Parameter will be natively typed
     *
     * @param list<string>|string $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getEditable(): ?bool
    {
        return $this->editable;
    }

    public function setEditable(?bool $editable): void
    {
        $this->editable = $editable;
    }

    public function getBlock(): ?string
    {
        return $this->block;
    }

    public function setBlock(?string $block): void
    {
        $this->block = $block;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function setSection(?string $section): void
    {
        $this->section = $section;
    }

    public function getTab(): ?string
    {
        return $this->tab;
    }

    public function setTab(?string $tab): void
    {
        $this->tab = $tab;
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function setOrder(?int $order): void
    {
        $this->order = $order;
    }

    public function getTabOrder(): ?int
    {
        return $this->tabOrder;
    }

    public function setTabOrder(?int $tabOrder): void
    {
        $this->tabOrder = $tabOrder;
    }

    public function getSectionOrder(): ?int
    {
        return $this->sectionOrder;
    }

    public function setSectionOrder(?int $sectionOrder): void
    {
        $this->sectionOrder = $sectionOrder;
    }

    public function getBlockOrder(): ?int
    {
        return $this->blockOrder;
    }

    public function setBlockOrder(?int $blockOrder): void
    {
        $this->blockOrder = $blockOrder;
    }

    /**
     * @return array<string, array<string, string>>|null
     *
     * @deprecated tag:v6.8.0 - Property will be removed. Use translations via helpTextSnippetKey instead
     */
    public function getHelpText(): ?array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0', 'getHelpTextSnippetKey'));

        return $this->helpText;
    }

    /**
     * @param array<string, array<string, string>>|null $helpText
     *
     * @deprecated tag:v6.8.0 - Property will be removed. Use translations via helpTextSnippetKey instead
     */
    public function setHelpText(?array $helpText): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'));

        $this->helpText = $helpText;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCustom(): ?array
    {
        return $this->custom;
    }

    /**
     * @param array<string, mixed>|null $custom
     */
    public function setCustom(?array $custom): void
    {
        $this->custom = $custom;
    }

    public function getScss(): ?bool
    {
        return $this->scss;
    }

    public function setScss(?bool $scss): void
    {
        $this->scss = $scss;
    }

    public function getFullWidth(): ?bool
    {
        return $this->fullWidth;
    }

    public function setFullWidth(?bool $fullWidth): void
    {
        $this->fullWidth = $fullWidth;
    }
}
