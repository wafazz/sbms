<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Everything on the dashboard, from aggregate queries. Cached for 60 seconds.
 */
class DashboardService
{
    public const CACHE_KEY = 'dashboard:summary:v1';

    public const CACHE_SECONDS = 60;

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->build());
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $monthStart = $today->startOfMonth();
        $monthEnd = $today->endOfMonth();
        $openStatuses = [ProjectStatus::Planning->value, ProjectStatus::Active->value, ProjectStatus::OnHold->value];

        // Project cards
        $activeProjects = Project::query()->where('status', ProjectStatus::Active->value)->count();
        $overdueProjects = Project::query()
            ->whereIn('status', $openStatuses)
            ->whereDate('end_date', '<', $today->toDateString())
            ->count();

        // Invoice cards. Invoiced = issued this month and no longer a draft or cancelled.
        $invoicedThisMonth = Invoice::query()
            ->whereBetween('issue_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
            ->sum('total');

        $paidThisMonth = Payment::query()
            ->whereBetween('payment_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        // Outstanding is every invoice with money still owed: sent, partially paid, or overdue.
        $outstanding = Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Sent->value,
                InvoiceStatus::PartiallyPaid->value,
                InvoiceStatus::Overdue->value,
            ])
            ->sum('balance');

        $overdueInvoices = Invoice::query()->where('status', InvoiceStatus::Overdue->value)->count();

        return [
            'as_of' => $today->toDateString(),
            'cards' => [
                'active_projects' => $activeProjects,
                'overdue_projects' => $overdueProjects,
                'invoiced_this_month' => $this->money($invoicedThisMonth),
                'paid_this_month' => $this->money($paidThisMonth),
                'outstanding_balance' => $this->money($outstanding),
                'overdue_invoices' => $overdueInvoices,
            ],
            'revenue' => $this->monthlyRevenue($today),
            'projects_by_status' => $this->projectsByStatus(),
            'deadlines' => $this->deadlines($today),
            'recent_invoices' => $this->recentInvoices(),
            'recent_activity' => $this->recentActivity(),
        ];
    }

    /**
     * Last 12 months, oldest first, with zeros for months with no activity.
     *
     * @return array{labels: array<int, string>, invoiced: array<int, string>, paid: array<int, string>}
     */
    private function monthlyRevenue(CarbonImmutable $today): array
    {
        $from = $today->subMonths(11)->startOfMonth();

        $invoiced = Invoice::query()
            ->selectRaw("DATE_FORMAT(issue_date, '%Y-%m') as ym, COALESCE(SUM(total),0) as amount")
            ->where('issue_date', '>=', $from->toDateString())
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
            ->groupBy('ym')
            ->pluck('amount', 'ym');

        $paid = Payment::query()
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as ym, COALESCE(SUM(amount),0) as amount")
            ->where('payment_date', '>=', $from->toDateString())
            ->groupBy('ym')
            ->pluck('amount', 'ym');

        $labels = [];
        $invoicedSeries = [];
        $paidSeries = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $from->addMonths($i);
            $key = $month->format('Y-m');
            $labels[] = $month->format('M Y');
            $invoicedSeries[] = $this->money($invoiced[$key] ?? 0);
            $paidSeries[] = $this->money($paid[$key] ?? 0);
        }

        return ['labels' => $labels, 'invoiced' => $invoicedSeries, 'paid' => $paidSeries];
    }

    /**
     * @return array<int, array{status: string, label: string, count: int}>
     */
    private function projectsByStatus(): array
    {
        $counts = Project::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(ProjectStatus::cases())
            ->map(fn (ProjectStatus $s) => [
                'status' => $s->value,
                'label' => $s->label(),
                'count' => (int) ($counts[$s->value] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Projects and milestones due in the next 7 and 14 days.
     *
     * @return array{within_7_days: array<int, array<string, mixed>>, within_14_days: array<int, array<string, mixed>>}
     */
    private function deadlines(CarbonImmutable $today): array
    {
        $end14 = $today->addDays(14)->toDateString();
        $todayStr = $today->toDateString();
        $end7 = $today->addDays(7)->toDateString();

        $projects = Project::query()
            ->whereIn('status', [ProjectStatus::Planning->value, ProjectStatus::Active->value, ProjectStatus::OnHold->value])
            ->whereBetween('end_date', [$todayStr, $end14])
            ->orderBy('end_date')
            ->limit(50)
            ->get(['id', 'code', 'name', 'end_date']);

        $milestones = ProjectMilestone::query()
            ->with('project:id,code')
            ->where('is_completed', false)
            ->whereBetween('due_date', [$todayStr, $end14])
            ->orderBy('due_date')
            ->limit(50)
            ->get(['id', 'project_id', 'title', 'due_date']);

        $items = collect()
            ->merge($projects->map(fn (Project $p) => [
                'type' => 'project',
                'title' => $p->name,
                'project_code' => $p->code,
                'project_id' => $p->id,
                'date' => $p->end_date->toDateString(),
            ]))
            ->merge($milestones->map(fn (ProjectMilestone $m) => [
                'type' => 'milestone',
                'title' => $m->title,
                'project_code' => $m->project?->code,
                'project_id' => $m->project_id,
                'date' => $m->due_date->toDateString(),
            ]))
            ->sortBy('date')
            ->values();

        return [
            'within_7_days' => $items->filter(fn ($i) => $i['date'] <= $end7)->values()->all(),
            'within_14_days' => $items->values()->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentInvoices(): array
    {
        return Invoice::query()
            ->with('client:id,name')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'invoice_number', 'client_id', 'status', 'total', 'balance', 'issue_date'])
            ->map(fn (Invoice $i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'client' => $i->client?->name,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'total' => $this->money($i->total),
                'balance' => $this->money($i->balance),
                'issue_date' => $i->issue_date->toDateString(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(): array
    {
        return Activity::query()
            ->with(['project:id,code,name', 'user:id,name'])
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'description' => $a->description,
                'project_id' => $a->project_id,
                'project_code' => $a->project?->code,
                'user' => $a->user?->name,
                'created_at' => $a->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** Money as a fixed two-decimal string, never a float. */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
