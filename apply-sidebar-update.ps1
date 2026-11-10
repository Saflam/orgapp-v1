$ErrorActionPreference = 'Stop'
$path = 'Modules/Core/resources/views/layouts/organization-admin.blade.php'

if (-not (Test-Path $path)) {
    throw "Could not find $path. Run this script from the Laravel project root."
}

$content = Get-Content -Raw -Path $path

$oldPermission = @'
        $canViewFinanceIncomeStatement = $financeModuleEnabled
            && \Illuminate\Support\Facades\Route::has('finance.reports.income-statement')
            && $membershipAuthorization->can($layoutUser, 'finance.report.view', $membershipOrganizationContext);
'@
$newPermission = @'
        $canViewFinanceIncomeStatement = $financeModuleEnabled
            && \Illuminate\Support\Facades\Route::has('finance.reports.income-statement')
            && $membershipAuthorization->can($layoutUser, 'finance.report.view', $membershipOrganizationContext);
        $canViewFinanceBalanceSheet = $financeModuleEnabled
            && \Illuminate\Support\Facades\Route::has('finance.reports.balance-sheet')
            && $membershipAuthorization->can($layoutUser, 'finance.report.view', $membershipOrganizationContext);
'@

if (-not $content.Contains($oldPermission)) {
    if ($content.Contains('$canViewFinanceBalanceSheet')) {
        Write-Host 'Balance Sheet permission variable already exists; skipping that change.'
    } else {
        throw 'Could not find the expected Finance Income Statement permission block. No changes made.'
    }
} else {
    $content = $content.Replace($oldPermission, $newPermission)
}

$oldGroup = '@if ($canViewFinanceTrialBalance || $canViewFinanceIncomeStatement)'
$newGroup = '@if ($canViewFinanceTrialBalance || $canViewFinanceIncomeStatement || $canViewFinanceBalanceSheet)'

if ($content.Contains($oldGroup)) {
    $content = $content.Replace($oldGroup, $newGroup)
} elseif (-not $content.Contains($newGroup)) {
    throw 'Could not find the Reports sidebar visibility condition. No changes made.'
}

$oldLink = @'
                            @if ($canViewFinanceIncomeStatement)
                                <a class="nav-link {{ request()->routeIs('finance.reports.income-statement') ? 'active' : '' }}" href="{{ route('finance.reports.income-statement') }}">
                                    <span class="nav-icon" aria-hidden="true">▤</span><span>Income Statement</span>
                                </a>
                            @endif
'@
$newLink = @'
                            @if ($canViewFinanceIncomeStatement)
                                <a class="nav-link {{ request()->routeIs('finance.reports.income-statement') ? 'active' : '' }}" href="{{ route('finance.reports.income-statement') }}">
                                    <span class="nav-icon" aria-hidden="true">▤</span><span>Income Statement</span>
                                </a>
                            @endif
                            @if ($canViewFinanceBalanceSheet)
                                <a class="nav-link {{ request()->routeIs('finance.reports.balance-sheet') ? 'active' : '' }}" href="{{ route('finance.reports.balance-sheet') }}">
                                    <span class="nav-icon" aria-hidden="true">▦</span><span>Balance Sheet</span>
                                </a>
                            @endif
'@

if ($content.Contains($oldLink)) {
    $content = $content.Replace($oldLink, $newLink)
} elseif (-not $content.Contains("finance.reports.balance-sheet') ? 'active'")) {
    throw 'Could not find the Income Statement sidebar link. No changes made.'
}

Set-Content -Path $path -Value $content -NoNewline
Write-Host 'Reports sidebar updated with the Balance Sheet link.'
