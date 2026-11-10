<div class="finance-balance-sheet__table-wrap">
    <table class="finance-balance-sheet__table">
        <thead>
            <tr>
                <th scope="col">Account</th>
                <th scope="col" class="is-numeric">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td>
                        <span>{{ $account['code'] }}</span>
                        <span> — {{ $account['name'] }}</span>
                    </td>
                    <td class="is-numeric">{{ $account['amount'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="finance-balance-sheet__empty">{{ $emptyMessage }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
