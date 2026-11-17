<?php

namespace Modules\Events\Services;

use Illuminate\Support\Str;
use Modules\Core\Models\Organization;
use Modules\Events\Models\Event;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventReportExportService
{
    public function __construct(private readonly EventReportService $reports)
    {
    }

    public function organizationSummary(Organization $organization, array $filters): StreamedResponse
    {
        $summary = $this->reports->organizationSummary($organization, $filters);
        $rows = [];
        $this->flatten($summary, '', $rows, ['filters', 'notes', 'generated_at']);

        return $this->csv('events-organization-summary', ['Metric', 'Value'], $this->keyValueRows($rows));
    }

    public function categories(Organization $organization, array $filters): StreamedResponse
    {
        $summary = $this->reports->categorySummary($organization, $filters);
        $categories = array_map(fn (array $category): array => $this->flattenRow($category), $summary['categories'] ?? []);
        $headers = $categories === []
            ? ['category_id', 'category_name', 'event_count']
            : array_values(array_unique(array_merge(...array_map('array_keys', $categories))));

        $rows = (function () use ($categories, $headers): \Generator {
            foreach ($categories as $category) {
                yield array_map(fn (string $header) => $category[$header] ?? '', $headers);
            }
        })();

        return $this->csv('events-category-summary', $headers, $rows);
    }

    public function registrations(Organization $organization, Event $event, array $filters): StreamedResponse
    {
        $query = $this->reports->registrationQuery($organization, $event, $filters)
            ->with('inviter:id,name');

        $rows = (function () use ($query): \Generator {
            foreach ($query->lazyById(500) as $participant) {
                yield [
                    $participant->id,
                    $participant->participant_type,
                    $participant->name,
                    $participant->email,
                    $participant->phone,
                    $participant->status,
                    $participant->invited_at?->toIso8601String(),
                    $participant->registered_at?->toIso8601String(),
                    $participant->inviter?->name,
                ];
            }
        })();

        return $this->csv($this->eventFilename($event, 'registrations'), [
            'Participant ID', 'Participant Type', 'Name', 'Email', 'Phone', 'Status',
            'Invited At', 'Registered At', 'Invited By',
        ], $rows);
    }

    public function attendance(Organization $organization, Event $event, array $filters): StreamedResponse
    {
        $query = $this->reports->attendanceQuery($organization, $event, $filters);

        $rows = (function () use ($query): \Generator {
            foreach ($query->lazyById(500) as $scan) {
                yield [
                    $scan->id,
                    $scan->pass?->id,
                    $scan->pass?->participant?->name,
                    $scan->attendee?->name,
                    $scan->action,
                    $scan->quantity,
                    $scan->scanned_at?->toIso8601String(),
                    $scan->scanner?->name,
                ];
            }
        })();

        return $this->csv($this->eventFilename($event, 'attendance'), [
            'Scan ID', 'Pass ID', 'Participant', 'Rostered Attendee', 'Action',
            'Quantity', 'Scanned At', 'Scanned By',
        ], $rows);
    }

    public function passes(Organization $organization, Event $event, array $filters): StreamedResponse
    {
        $query = $this->reports->passQuery($organization, $event, $filters, false)
            ->withCount('attendees');

        $rows = (function () use ($query): \Generator {
            foreach ($query->lazyById(500) as $pass) {
                yield [
                    $pass->id,
                    $pass->participant?->name,
                    $pass->participant?->email,
                    $pass->participant?->phone,
                    $pass->participant?->participant_type,
                    $pass->participant?->status,
                    $pass->status,
                    $pass->capacity,
                    $pass->current_inside,
                    $pass->attendees_count,
                    $pass->issued_at?->toIso8601String(),
                    $pass->revoked_at?->toIso8601String(),
                    $pass->notification_delivery_status,
                    $pass->notification_sent_at?->toIso8601String(),
                    $pass->notification_failed_at?->toIso8601String(),
                ];
            }
        })();

        return $this->csv($this->eventFilename($event, 'passes'), [
            'Pass ID', 'Participant', 'Email', 'Phone', 'Participant Type', 'Participant Status',
            'Pass Status', 'Capacity', 'Currently Inside', 'Rostered Attendees', 'Issued At',
            'Revoked At', 'Delivery Status', 'Notification Sent At', 'Notification Failed At',
        ], $rows);
    }

    private function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }

            // UTF-8 BOM improves compatibility with Excel when names contain non-ASCII text.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers, ',', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($output, array_map([$this, 'safeCell'], $row), ',', '"', '\\');
            }

            fclose($output);
        }, $filename.'-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function safeCell(mixed $value): string|int|float
    {
        if ($value === null) {
            return '';
        }

        if (! is_string($value)) {
            return is_scalar($value) ? $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Prevent spreadsheet formula execution for untrusted text values.
        return preg_match('/^[\s]*[=+@\-]/u', $value) === 1 ? "'".$value : $value;
    }

    private function eventFilename(Event $event, string $suffix): string
    {
        return 'events-'.Str::slug($event->title ?: 'event').'-'.$suffix;
    }

    private function flatten(array $data, string $prefix, array &$result, array $skip = []): void
    {
        foreach ($data as $key => $value) {
            if ($prefix === '' && in_array((string) $key, $skip, true)) {
                continue;
            }

            $name = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $this->flatten($value, $name, $result);
            } elseif (is_scalar($value) || $value === null) {
                $result[$name] = $value;
            }
        }
    }

    private function flattenRow(array $data): array
    {
        $result = [];
        $this->flatten($data, '', $result);

        return $result;
    }

    private function keyValueRows(array $values): \Generator
    {
        foreach ($values as $key => $value) {
            yield [$key, $value];
        }
    }
}
