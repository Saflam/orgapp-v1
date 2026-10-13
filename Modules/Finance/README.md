# Finance Module

General-purpose financial foundation for Saradhi.

The module is intentionally independent of Member and other optional modules. Other modules integrate through Finance contracts/events rather than Finance depending on them.

## Double-entry Journal Foundation

Finance journals are posted as balanced double-entry accounting transactions.

- Every journal belongs to an organization, Finance scope, and financial period.
- Every journal contains at least two lines.
- Each line contains either a debit or a credit, never both.
- Total debits must equal total credits before a journal is posted.
- Accounts must belong to the same organization and Finance scope as the journal.
- Inactive accounts cannot be posted to.
- Journals cannot be posted outside their financial period.
- Closed financial periods reject new journals.
- Posted journals and their lines are immutable; corrections will be implemented through reversal journals.

The `FinanceJournalService` is the domain entry point for posting journals.
