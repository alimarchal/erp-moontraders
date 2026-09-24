{{--
    Permission matrix: every permission arranged like the app's own screens
    (Settings sections / Reports sections), one row per screen, one pill per action.

    Needs an enclosing Alpine scope built with window.permissionMatrix(...)
    (see settings.partials.permission-matrix-script). Posts permissions[] = ids,
    exactly like the old selector. Pills granted by a selected role are shown
    green ("role") and are not posted, so only extra permissions are saved.

    @var array $catalog  App\Services\PermissionCatalog::build()
    @var bool  $withRoles  true on the user form (shows the "via role" state)
--}}
@php
    $withRoles = $withRoles ?? false;
    $danger = ['delete', 'reverse', 'revert', 'cancel', 'bulk-update'];
@endphp

<div class="pm-toolbar">
    <div class="ak-search">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
        <input type="search" x-model.debounce.150ms="search" placeholder="Find a screen, report or permission (e.g. ledger, post, vehicle)" aria-label="Search permissions" @keydown.enter.prevent>
    </div>
    <div class="ak-seg ak-seg-sm" role="group" aria-label="Show">
        <button type="button" @click="onlyGranted = false" :class="!onlyGranted && 'is-on'" :aria-pressed="!onlyGranted">All</button>
        <button type="button" @click="onlyGranted = true" :class="onlyGranted && 'is-on'" :aria-pressed="onlyGranted">Granted only</button>
    </div>
    <div class="pm-legend" aria-hidden="true">
        <span class="pm-act is-on"><span class="pm-tick">✓</span> Given</span>
        @if ($withRoles)<span class="pm-act is-role"><span class="pm-tick">✓</span> <small>role</small> From role</span>@endif
        <span class="pm-act">Not given</span>
    </div>
</div>

<div class="pm-tabs" role="tablist" x-show="q() === ''">
    @foreach ($catalog as $module)
        @php $moduleIds = collect($module['sections'])->flatMap(fn ($s) => collect($s['groups'])->flatMap(fn ($g) => collect($g['permissions'])->pluck('id')))->values(); @endphp
        <button type="button" role="tab" @click="tab = @js($module['key'])" :class="tab === @js($module['key']) && 'is-active'" :aria-selected="tab === @js($module['key'])">
            {{ $module['label'] }}
            <span class="ak-count" x-text="grantedIn({{ \Illuminate\Support\Js::from($moduleIds) }}) + ' / {{ $moduleIds->count() }}'"></span>
        </button>
    @endforeach
</div>

<div class="pm-scroll">
    @foreach ($catalog as $module)
        @php $moduleIds = collect($module['sections'])->flatMap(fn ($s) => collect($s['groups'])->flatMap(fn ($g) => collect($g['permissions'])->pluck('id')))->values(); @endphp
        <div x-show="q() !== '' || tab === @js($module['key'])" data-module="{{ $module['key'] }}">
            <p class="pm-hint" x-show="q() === ''">
                {{ $module['hint'] }}
                <span class="pm-links" style="display:inline-flex; margin-left:8px">
                    <button type="button" @click="setMany({{ \Illuminate\Support\Js::from($moduleIds) }}, true)">Give all {{ strtolower($module['label']) }}</button>
                    <button type="button" class="rem" @click="setMany({{ \Illuminate\Support\Js::from($moduleIds) }}, false)">Clear all</button>
                </span>
            </p>

            @foreach ($module['sections'] as $section)
                @php
                    $rows = collect($section['groups'])->map(function ($group) use ($section, $module) {
                        $hay = strtolower(implode(' ', [
                            $module['label'], $section['label'], $group['label'], $group['key'],
                            ...collect($group['permissions'])->flatMap(fn ($p) => [$p['name'], $p['label']])->all(),
                        ]));

                        return ['ids' => collect($group['permissions'])->pluck('id')->values()->all(), 'hay' => $hay];
                    });
                    $sectionIds = $rows->flatMap(fn ($r) => $r['ids'])->values();
                @endphp
                <div class="pm-section" x-show="sectionVisible({{ \Illuminate\Support\Js::from($rows) }})">
                    <div class="pm-section-head">
                        <h4>
                            @if (count($catalog) > 1)<span x-show="q() !== ''" style="margin:0 6px 0 0">{{ $module['label'] }} ›</span>@endif{{ $section['label'] }}
                            <span x-text="grantedIn({{ \Illuminate\Support\Js::from($sectionIds) }}) + ' of {{ $sectionIds->count() }} given'"></span>
                        </h4>
                        <div class="pm-links">
                            <button type="button" @click="setMany({{ \Illuminate\Support\Js::from($sectionIds) }}, true)">Give all</button>
                            <button type="button" class="rem" @click="setMany({{ \Illuminate\Support\Js::from($sectionIds) }}, false)">Clear</button>
                        </div>
                    </div>

                    @foreach ($section['groups'] as $i => $group)
                        @php $row = $rows[$i]; @endphp
                        <div class="pm-row" x-show="rowVisible({{ \Illuminate\Support\Js::from($row['ids']) }}, @js($row['hay']))">
                            <div class="pm-row-name">
                                <span>{{ $group['label'] }}</span>
                                @if (count($row['ids']) > 1)
                                    <button type="button" @click="toggleRow({{ \Illuminate\Support\Js::from($row['ids']) }})"
                                        x-text="rowFull({{ \Illuminate\Support\Js::from($row['ids']) }}) ? 'none' : 'all'" title="Give or remove every action on this screen"></button>
                                @endif
                            </div>
                            <div class="pm-acts">
                                @foreach ($group['permissions'] as $permission)
                                    @php $isDanger = in_array($permission['action'], $danger, true); @endphp
                                    <label class="pm-act {{ $isDanger ? 'is-danger' : '' }}"
                                        :class="{ 'is-on': isOn('{{ $permission['id'] }}'), 'is-role': viaRole('{{ $permission['id'] }}') }"
                                        title="{{ $permission['help'] ? $permission['help'].' — ' : '' }}{{ $permission['name'] }}"
                                        @if ($withRoles) :title="viaRole('{{ $permission['id'] }}') ? 'Given by role: ' + inheritedMap['{{ $permission['id'] }}'].join(', ') + ' — {{ $permission['name'] }}' : @js(($permission['help'] ? $permission['help'].' — ' : '').$permission['name'])" @endif>
                                        <input type="checkbox" name="permissions[]" value="{{ $permission['id'] }}" x-model="permissions"
                                            class="permission-checkbox" x-show="!viaRole('{{ $permission['id'] }}')">
                                        <span class="pm-tick" x-show="viaRole('{{ $permission['id'] }}')" x-cloak>✓</span>
                                        {{ $permission['label'] }}
                                        @if ($withRoles)<small x-show="viaRole('{{ $permission['id'] }}')" x-cloak>role</small>@endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endforeach

    @php
        $allRows = collect($catalog)->flatMap(fn ($m) => collect($m['sections'])->flatMap(fn ($s) => collect($s['groups'])->map(fn ($g) => [
            'ids' => collect($g['permissions'])->pluck('id')->values()->all(),
            'hay' => strtolower(implode(' ', [$m['label'], $s['label'], $g['label'], $g['key'], ...collect($g['permissions'])->flatMap(fn ($p) => [$p['name'], $p['label']])->all()])),
        ])))->values();
    @endphp
    <div class="pm-empty" x-show="! sectionVisible({{ \Illuminate\Support\Js::from($allRows) }})" x-cloak>
        <span x-show="q() !== ''">No permission matches “<span x-text="search"></span>”.</span>
        <span x-show="q() === ''">Nothing given yet. Switch to <b>All</b> to choose permissions.</span>
    </div>
</div>

@include('settings.partials.permission-matrix-script')
