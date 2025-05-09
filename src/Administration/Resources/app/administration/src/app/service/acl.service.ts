/**
 * @sw-package framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class AclService {
    isAdmin(): boolean {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        return !!Shopware.Store.get('session').currentUser?.admin;
    }

    can(privilegeKey: string): boolean {
        if (this.isAdmin() || !privilegeKey) {
            return true;
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        return (Shopware.Store.get('session').userPrivileges as string[]).includes(privilegeKey);
    }

    hasAccessToRoute(path: string): boolean {
        const route = path.replace(/\./g, '/');
        if (route === '/sw/settings/index') {
            return this.hasActiveSettingModules();
        }

        if (!Shopware?.Application?.view?.root?.$router) {
            return true;
        }

        const router = Shopware.Application.view.root.$router;
        // @ts-expect-error - meta is not defined in the type
        const match = router.resolve(route) as { meta?: { privilege: string } };

        if (!match.meta) {
            return true;
        }

        return this.can(match.meta.privilege);
    }

    hasActiveSettingModules(): boolean {
        // @ts-expect-error
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-argument
        const groups = Object.values(Shopware.Store.get('settingsItems').settingsGroups) as [[{ privilege?: string }]];

        let hasActive = false;

        groups.forEach((modules) => {
            modules.forEach((module) => {
                if (!module.privilege) {
                    hasActive = true;
                } else if (this.can(module.privilege)) {
                    hasActive = true;
                }
            });
        });

        return hasActive;
    }

    get privileges(): string[] {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        return Shopware.Store.get('session').userPrivileges as string[];
    }
}
