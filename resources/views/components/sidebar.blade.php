<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="{{ route('home') }}">ATTENDANCE APP</a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="{{ route('home') }}">St</a>
        </div>
        <ul class="sidebar-menu">

            <li class="nav-item">
                <a href="{{ route('home') }}" class="nav-link"><i class="fas fa-fire"></i><span>Dashboard</span></a>
            </li>

            <li class="nav-item ">
                <a href="{{ route('users.index') }}" class="nav-link "><i class="fas fa-users"></i>
                    <span>Users</span></a>
            </li>

            <li class="nav-item">
                <a href="{{ route('companies.show', 1) }}" class="nav-link">
                    <i class="fas fa-building"></i>
                    <span>Company</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('attendances.index') }}" class="nav-link">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendances</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('permissions.index') }}" class="nav-link">
                    <i class="fas fa-file-alt"></i>
                    <span>Permission</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('reimbursements.index') }}" class="nav-link">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Reimbursement</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('shifts.index') }}" class="nav-link">
                    <i class="fas fa-clock"></i>
                    <span>Shift</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('overtimes.index') }}" class="nav-link">
                    <i class="fas fa-business-time"></i>
                    <span>Overtime</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('qr_absens.index') }}" class="nav-link">
                    <i class="fas fa-qrcode"></i>
                    <span>QR Absen</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('verifikasi.index') }}" class="nav-link">
                    <i class="fas fa-user-check"></i>
                    <span>Verifikasi Pensiun</span>
                </a>
            </li>

    </aside>
</div>
