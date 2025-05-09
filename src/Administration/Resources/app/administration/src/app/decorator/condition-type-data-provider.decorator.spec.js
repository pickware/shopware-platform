/**
 * @sw-package fundamentals@after-sales
 */
import 'src/app/decorator/condition-type-data-provider.decorator';
import RuleConditionService from 'src/app/service/rule-condition.service';

describe('entity-collection.data.ts', () => {
    beforeAll(async () => {
        Shopware.Service().register('ruleConditionDataProviderService', () => {
            return new RuleConditionService();
        });
    });

    it('should configure rule awareness configurations', async () => {
        const expectedConfigs = [
            'shippingMethodPrices',
            'shippingMethodPriceCalculations',
            'promotionDiscounts',
            'promotionSetGroups',
            'cartPromotions',
            'orderPromotions',
            'personaPromotions',
        ];

        const ruleAwareness = Shopware.Service('ruleConditionDataProviderService').awarenessConfiguration;

        expect(Object.keys(ruleAwareness)).toHaveLength(expectedConfigs.length);

        expectedConfigs.forEach((config) => {
            expect(ruleAwareness).toHaveProperty(config);
        });
    });

    it('should register conditions with correct scope', async () => {
        const condition = Shopware.Service('ruleConditionDataProviderService').getByType('language');

        expect(condition).toBeDefined();
        expect(condition.scopes).toEqual(['global']);
    });

    it('should add app script conditions', async () => {
        Shopware.Service('ruleConditionDataProviderService').addScriptConditions([
            {
                id: 'bar',
                name: 'foo',
                group: 'misc',
                config: {},
            },
        ]);

        const condition = Shopware.Service('ruleConditionDataProviderService').getByType('bar');

        expect(condition.component).toBe('sw-condition-script');
        expect(condition.type).toBe('scriptRule');
        expect(condition.label).toBe('foo');
    });
});
