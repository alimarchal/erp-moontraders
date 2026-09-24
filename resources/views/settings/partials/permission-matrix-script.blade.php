{{-- Alpine state for settings.partials.permission-matrix (loaded once per page). --}}
@once
    @push('scripts')
        <script>
            /**
             * config.permissions   ids (strings) posted as permissions[]
             * config.rolePermissions  { roleId: [permissionId, ...] }  (user form only)
             * config.roleMap        { roleId: roleName }                (user form only)
             */
            window.permissionMatrix = function (config) {
                return {
                    permissions: (config.permissions || []).map(String),
                    rolePermissions: config.rolePermissions || {},
                    roleMap: config.roleMap || {},
                    inheritedMap: {},
                    search: '',
                    onlyGranted: false,
                    tab: config.tab || 'settings',
                    /** Rebuild "permission -> roles that give it" for the given role ids. */
                    refreshInherited(roleIds) {
                        const map = {};
                        (roleIds || []).forEach((roleId) => {
                            (this.rolePermissions[roleId] || []).forEach((id) => {
                                (map[id] = map[id] || []).push(this.roleMap[roleId] || roleId);
                            });
                        });
                        this.inheritedMap = map;
                    },
                    q() { return this.search.trim().toLowerCase(); },
                    isOn(id) { return this.permissions.includes(id); },
                    viaRole(id) { return !!this.inheritedMap[id] && !this.permissions.includes(id); },
                    granted(id) { return this.permissions.includes(id) || !!this.inheritedMap[id]; },
                    grantedIn(ids) { return ids.filter((id) => this.granted(id)).length; },
                    rowFull(ids) { return ids.every((id) => this.granted(id)); },
                    setMany(ids, on) {
                        if (on) {
                            const add = ids.filter((id) => !this.permissions.includes(id) && !this.inheritedMap[id]);
                            this.permissions = this.permissions.concat(add);
                        } else {
                            this.permissions = this.permissions.filter((id) => !ids.includes(id));
                        }
                    },
                    toggleRow(ids) { this.setMany(ids, !this.rowFull(ids)); },
                    rowVisible(ids, hay) {
                        if (this.onlyGranted && !ids.some((id) => this.granted(id))) return false;
                        const q = this.q();
                        return q === '' || q.split(/\s+/).every((word) => hay.includes(word));
                    },
                    sectionVisible(rows) { return rows.some((row) => this.rowVisible(row.ids, row.hay)); },
                };
            };
        </script>
    @endpush
@endonce
