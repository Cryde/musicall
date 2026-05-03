<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\ApiResource\BandSpace\AgendaItem;
use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\Task;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\TaskRepository;
use DateTimeImmutable;
use DateTimeInterface;

readonly class AgendaAggregator
{
    public function __construct(
        private AgendaEntryRepository $agendaEntryRepository,
        private TaskRepository $taskRepository,
        private FinanceEntryRepository $financeEntryRepository,
    ) {
    }

    /**
     * @return AgendaItem[]
     */
    public function aggregate(BandSpace $bandSpace, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $items = [
            ...array_map(fn(AgendaEntry $e): AgendaItem => $this->buildManual($bandSpace, $e), $this->agendaEntryRepository->findUpcomingForBand($bandSpace, $from, $to)),
            ...array_map(fn(Task $t): AgendaItem => $this->buildTask($bandSpace, $t), $this->taskRepository->findUpcomingForBand($bandSpace, $from, $to)),
            ...array_map(fn(FinanceEntry $f): AgendaItem => $this->buildFinance($bandSpace, $f), $this->financeEntryRepository->findUpcomingForBand($bandSpace, $from, $to)),
        ];

        usort(
            $items,
            fn(AgendaItem $a, AgendaItem $b): int => strcmp($a->datetime, $b->datetime) ?: strcmp($a->source, $b->source) ?: strcmp($a->sourceId, $b->sourceId),
        );

        return $items;
    }

    private function buildManual(BandSpace $bandSpace, AgendaEntry $entry): AgendaItem
    {
        $item = new AgendaItem();
        $item->id = 'manual-' . (string) $entry->id;
        $item->bandSpaceId = (string) $bandSpace->id;
        $item->source = 'manual';
        $item->sourceId = (string) $entry->id;
        $item->datetime = $entry->eventDatetime->format(DateTimeInterface::ATOM);
        $item->title = $entry->title;
        $item->description = $entry->description;
        $item->metadata = [
            'location' => $entry->location,
        ];

        return $item;
    }

    private function buildTask(BandSpace $bandSpace, Task $task): AgendaItem
    {
        $item = new AgendaItem();
        $item->id = 'task-' . (string) $task->id;
        $item->bandSpaceId = (string) $bandSpace->id;
        $item->source = 'task';
        $item->sourceId = (string) $task->id;
        $item->datetime = $task->dueDate?->format(DateTimeInterface::ATOM) ?? '';
        $item->title = $task->title;
        $item->description = $task->description;
        $item->metadata = [
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'category_name' => $task->category?->name,
        ];

        return $item;
    }

    private function buildFinance(BandSpace $bandSpace, FinanceEntry $entry): AgendaItem
    {
        $item = new AgendaItem();
        $item->id = 'finance-' . (string) $entry->id;
        $item->bandSpaceId = (string) $bandSpace->id;
        $item->source = 'finance';
        $item->sourceId = (string) $entry->id;
        $item->datetime = DateTimeImmutable::createFromInterface($entry->date)->setTime(0, 0)->format(DateTimeInterface::ATOM);
        $item->title = $entry->label;
        $item->description = null;
        $item->metadata = [
            'type' => $entry->type->value,
            'status' => $entry->status->value,
            'scope' => $entry->scope->value,
            'amount' => $entry->amount,
            'amount_min' => $entry->amountMin,
            'amount_max' => $entry->amountMax,
            'category_name' => $entry->category->name,
        ];

        return $item;
    }
}
