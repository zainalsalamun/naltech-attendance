<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ringkasan') — Naltech Attendance</title>
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('naltech.theme') === 'dark' ? 'dark' : 'light'; } catch (_) {}</script>
    <link rel="stylesheet" href="{{ asset('library/ionicons201/css/ionicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/workspace.css') }}?v={{ filemtime(public_path('css/workspace.css')) }}">
    <script src="{{ asset('js/workspace.js') }}?v={{ filemtime(public_path('js/workspace.js')) }}" defer></script>
</head>
<body class="workspace-app">
<a class="skip-link" href="#main">Lewati ke konten</a>
<div class="workspace-layout">
    <aside class="sidebar" id="workspace-navigation" aria-label="Navigasi Workspace" data-storage-prefix="naltech.sidebar.groups.{{ auth()->id() }}.{{ $company->id }}">
        <div class="sidebar-header">
            <a class="brand" href="{{ route('home') }}">naltech<span>ATTENDANCE</span></a>
            <button type="button" class="sidebar-toggle-btn" id="sidebar-toggle-btn" aria-label="Minimize sidebar" title="Minimize sidebar">
                <i class="ion-ios-arrow-back desktop-icon" aria-hidden="true"></i>
                <i class="ion-android-close mobile-icon" aria-hidden="true"></i>
            </button>
        </div>

        @php
            $navGroups = [
                [
                    'id' => 'group-karyawan',
                    'title' => 'Karyawan',
                    'icon' => 'ion-ios-people-outline',
                    'items' => [
                        [
                            'label' => 'Data karyawan',
                            'route' => 'users.index',
                            'params' => [],
                            'patterns' => ['users.*'],
                            'allowed' => $manager,
                        ],
                        [
                            'label' => 'Unit organisasi',
                            'route' => 'units.index',
                            'params' => [],
                            'patterns' => ['units.*'],
                            'allowed' => $manager,
                        ],
                        [
                            'label' => 'Verifikasi pensiun',
                            'route' => 'verifikasi.index',
                            'params' => [],
                            'patterns' => ['verifikasi.*'],
                            'allowed' => $manager,
                        ],
                    ],
                ],
                [
                    'id' => 'group-kehadiran',
                    'title' => 'Kehadiran',
                    'icon' => 'ion-ios-calendar-outline',
                    'items' => [
                        [
                            'label' => 'Data kehadiran',
                            'route' => 'attendances.index',
                            'params' => [],
                            'patterns' => ['attendances.index'],
                            'allowed' => $manager,
                        ],
                        [
                            'label' => 'Koreksi kehadiran',
                            'route' => 'attendance-corrections.index',
                            'params' => [],
                            'patterns' => ['attendance-corrections.*', 'attendances.edit'],
                            'allowed' => $manager,
                        ],
                        [
                            'label' => 'Rekap kehadiran',
                            'route' => 'attendance-recap.index',
                            'params' => [],
                            'patterns' => ['attendance-recap.*'],
                            'allowed' => $manager,
                        ],
                        [
                            'label' => 'QR absensi',
                            'route' => 'qr_absens.index',
                            'params' => [],
                            'patterns' => ['qr_absens.*'],
                            'allowed' => $manager,
                        ],
                        [
                            'label' => 'Pendaftaran wajah',
                            'route' => 'face-enrollments.index',
                            'params' => [],
                            'patterns' => ['face-enrollments.*'],
                            'allowed' => in_array(auth()->user()->role, ['admin', 'super admin', 'hrd'], true),
                        ],
                    ],
                ],
                [
                    'id' => 'group-jadwal',
                    'title' => 'Jadwal kerja',
                    'icon' => 'ion-ios-clock-outline',
                    'items' => [
                        [
                            'label' => 'Master shift',
                            'route' => 'shifts.index',
                            'params' => [],
                            'patterns' => ['shifts.*'],
                            'allowed' => $manager,
                        ],
                        [
                            'label' => 'Jadwal efektif',
                            'route' => 'effective-shifts.index',
                            'params' => [],
                            'patterns' => ['effective-shifts.*'],
                            'allowed' => $manager,
                        ],
                        [
                            'label' => 'Kalender kerja',
                            'route' => 'work-calendar.index',
                            'params' => [],
                            'patterns' => ['work-calendar.*'],
                            'allowed' => $manager,
                        ],
                        [
                            'label' => 'Lokasi kerja',
                            'route' => 'companies.show',
                            'params' => [$company],
                            'patterns' => ['companies.show'],
                            'allowed' => $manager,
                        ],
                    ],
                ],
                [
                    'id' => 'group-pengajuan',
                    'title' => 'Pengajuan',
                    'icon' => 'ion-ios-paper-outline',
                    'items' => [
                        [
                            'label' => 'Cuti / izin',
                            'route' => 'permissions.index',
                            'params' => [],
                            'patterns' => ['permissions.*', 'leave-balances.*'],
                            'allowed' => $manager,
                        ],
                        [
                            'label' => 'Lembur',
                            'route' => 'overtimes.index',
                            'params' => [],
                            'patterns' => ['overtimes.*'],
                            'allowed' => $manager,
                        ],
                        [
                            'label' => 'Reimbursement',
                            'route' => 'reimbursements.index',
                            'params' => [],
                            'patterns' => ['reimbursements.*'],
                            'allowed' => $manager,
                        ],
                    ],
                ],
                [
                    'id' => 'group-pengaturan',
                    'title' => 'Pengaturan',
                    'icon' => 'ion-ios-gear-outline',
                    'items' => [
                        [
                            'label' => 'Perusahaan',
                            'route' => 'companies.edit',
                            'params' => [$company],
                            'patterns' => ['companies.edit', 'companies.update'],
                            'allowed' => $manager,
                        ],
                        [
                            'label' => 'Jenis cuti',
                            'route' => 'leave-types.index',
                            'params' => [],
                            'patterns' => ['leave-types.*'],
                            'allowed' => $manager,
                        ],
                        [
                            'label' => 'Alur persetujuan',
                            'route' => 'approval-workflows.index',
                            'params' => [],
                            'patterns' => ['approval-workflows.*'],
                            'allowed' => $manager,
                        ],
                    ],
                ],
            ];
        @endphp

        <div class="sidebar-body">
            <nav class="sidebar-nav" aria-label="Navigasi utama">
                <div class="sidebar-single-item">
                    <a href="{{ route('home') }}"
                       @class(['sidebar-item-link', 'active' => request()->routeIs('home')])
                       @if(request()->routeIs('home')) aria-current="page" @endif>
                        <i class="ion-ios-grid-view-outline sidebar-item-icon" aria-hidden="true"></i>
                        <span class="sidebar-item-label">Ringkasan</span>
                    </a>
                </div>

                @foreach($navGroups as $group)
                    @php
                        $visibleItems = array_filter($group['items'], fn($item) => $item['allowed']);
                        if (empty($visibleItems)) continue;

                        $hasActiveChild = false;
                        foreach ($visibleItems as $item) {
                            if (request()->routeIs(...$item['patterns'])) {
                                $hasActiveChild = true;
                                break;
                            }
                        }
                    @endphp
                    <div class="sidebar-group {{ $hasActiveChild ? 'is-open has-active-child' : '' }}" data-group-id="{{ $group['id'] }}">
                        <button type="button"
                                class="sidebar-group-toggle"
                                id="btn-{{ $group['id'] }}"
                                aria-expanded="{{ $hasActiveChild ? 'true' : 'false' }}"
                                aria-controls="{{ $group['id'] }}"
                                data-has-active-child="{{ $hasActiveChild ? 'true' : 'false' }}">
                            <span class="sidebar-group-lead">
                                <i class="{{ $group['icon'] }} sidebar-group-icon" aria-hidden="true"></i>
                                <span class="sidebar-group-title">{{ $group['title'] }}</span>
                            </span>
                            <i class="ion-ios-arrow-down sidebar-group-chevron" aria-hidden="true"></i>
                        </button>
                        <div id="{{ $group['id'] }}"
                             class="sidebar-submenu"
                             role="region"
                             aria-labelledby="btn-{{ $group['id'] }}"
                             @if(!$hasActiveChild) hidden @endif>
                            @foreach($visibleItems as $item)
                                @php
                                    $isItemActive = request()->routeIs(...$item['patterns']);
                                    $itemUrl = !empty($item['params']) ? route($item['route'], $item['params']) : route($item['route']);
                                @endphp
                                <a href="{{ $itemUrl }}"
                                   @class(['sidebar-subitem', 'active' => $isItemActive])
                                   @if($isItemActive) aria-current="page" @endif>
                                    <span class="sidebar-subitem-label">{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
        </div>

        <div class="sidebar-footer">
            <div class="sidebar-account">
                <div class="sidebar-account-info">
                    <strong>{{ auth()->user()->name }}</strong>
                    <span>{{ $manager ? 'Pengelola perusahaan' : 'Akun karyawan' }}</span>
                </div>
                <div class="sidebar-account-actions">
                    <a href="{{ route('profile') }}" class="sidebar-account-link" @if(request()->routeIs('profile')) aria-current="page" @endif>
                        <i class="ion-ios-person-outline" aria-hidden="true"></i>
                        <span>Profil akun</span>
                    </a>
                    <form method="post" action="{{ route('logout') }}" class="sidebar-logout-form">
                        @csrf
                        <button type="submit" class="sidebar-logout-btn" aria-label="Keluar dari akun">
                            <i class="ion-log-out" aria-hidden="true"></i>
                            <span>Keluar</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>
    <div class="workspace-main">
        <header class="workspace-header">
            <button class="icon-button menu-toggle" aria-controls="workspace-navigation" aria-expanded="true" aria-label="Minimize sidebar" title="Minimize sidebar"><i class="ion-navicon" aria-hidden="true"></i></button>
            <div class="company-heading"><span class="company-icon"><i class="ion-ios-briefcase-outline" aria-hidden="true"></i></span><div><strong>{{ $company->name }}</strong><span>Workspace perusahaan</span></div></div>
            <div class="header-actions"><div class="theme-switch" role="group" aria-label="Tema aplikasi"><button type="button" data-theme-choice="light" aria-label="Tema terang" aria-pressed="true"><i class="ion-ios-sunny-outline" aria-hidden="true"></i><span>Light</span></button><button type="button" data-theme-choice="dark" aria-label="Tema gelap" aria-pressed="false"><i class="ion-ios-moon-outline" aria-hidden="true"></i><span>Dark</span></button></div><details class="account-menu"><summary aria-label="Menu akun {{ auth()->user()->name }}"><span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}</span></summary><div><strong>{{ auth()->user()->name }}</strong><a href="{{ route('profile') }}">Profil saya</a><form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Keluar akun</button></form></div></details></div>
        </header>
        <main id="main" class="workspace-content">@yield('content')</main>
    </div>
</div>
</body>
</html>
