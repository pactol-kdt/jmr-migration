import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/Components/ui/sheet';
import { cn } from '@/lib/utils';
import { router } from '@/inertia';
import { Filter, Search, X } from 'lucide-react';
import { useState } from 'react';

const textFields = [
    ['employee_id', 'Employee ID', 'employee_ids'],
    ['location', 'Location', 'locations'],
    ['project', 'Project', 'projects'],
    ['type', 'Type of Works', 'types'],
    ['category', 'Category', 'categories'],
    ['group', 'Group', 'groups'],
    ['member', 'Member', 'members'],
    ['person_in_charge', 'Person in charge', 'people_in_charge'],
];

export default function RecordFilters({
    local,
    setLocal,
    onApply,
    onReset,
    years = [],
    filterOptions = {},
    hideEmployee = false,
    hideProject = false,
    includeMember = false,
    allowAllYears = true,
}) {
    const [sheetOpen, setSheetOpen] = useState(false);
    const [optionsLoading, setOptionsLoading] = useState(false);
    const optionsReady = Array.isArray(filterOptions.locations);

    const fields = textFields.filter(([key]) => {
        if (key === 'employee_id' && hideEmployee) {
            return false;
        }

        if (key === 'member' && !includeMember) {
            return false;
        }

        if (key === 'project' && hideProject) {
            return false;
        }

        return true;
    });

    const chipFields = [
        ['year', 'Year'],
        ...fields.map(([key, label]) => [
            key,
            key === 'employee_id'
                ? 'Employee'
                : key === 'person_in_charge'
                  ? 'Person'
                  : label.replace(' ID', ''),
        ]),
        ['date_from', 'From'],
        ['date_to', 'To'],
    ];

    const apply = (event) => {
        event?.preventDefault?.();
        setSheetOpen(false);
        onApply(local);
    };

    const reset = () => {
        setSheetOpen(false);
        onReset();
    };

    const removeChip = (key) => {
        const next = { ...local, [key]: '' };
        setLocal(next);
        onApply(next);
    };

    const setField = (key) => (event) =>
        setLocal({ ...local, [key]: event.target.value });

    const activeChips = chipFields.filter(([key]) => Boolean(local[key]));

    return (
        <form onSubmit={apply} className="space-y-3">
            <div className="flex items-center gap-2">
                <div className="relative min-w-0 flex-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={local.search}
                        onChange={setField('search')}
                        placeholder="Search records…"
                        className="pl-9"
                    />
                </div>
                <Sheet
                    open={sheetOpen}
                    onOpenChange={(open) => {
                        setSheetOpen(open);

                        if (!open || optionsReady || optionsLoading) {
                            return;
                        }

                        setOptionsLoading(true);
                        router.reload({
                            only: ['filter_options'],
                            preserveState: true,
                            preserveScroll: true,
                            onFinish: () => setOptionsLoading(false),
                        });
                    }}
                >
                    <SheetTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            className={cn(
                                activeChips.length > 0 &&
                                    'border-primary text-primary',
                            )}
                        >
                            <Filter className="h-4 w-4" />
                            Filters
                            {activeChips.length > 0 && (
                                <Badge className="ml-1 h-5 min-w-5 justify-center rounded-full px-1.5">
                                    {activeChips.length}
                                </Badge>
                            )}
                        </Button>
                    </SheetTrigger>
                    <SheetContent className="flex w-full flex-col sm:max-w-md">
                        <SheetHeader>
                            <SheetTitle>Filters</SheetTitle>
                        </SheetHeader>
                        <div className="mt-6 grid flex-1 gap-4 overflow-y-auto pb-4">
                            {optionsLoading && (
                                <p className="text-sm text-muted-foreground">
                                    Loading filter choices…
                                </p>
                            )}
                            <div className="space-y-2">
                                <Label>Year</Label>
                                <Select
                                    value={
                                        local.year
                                            ? String(local.year)
                                            : allowAllYears
                                              ? 'all'
                                              : undefined
                                    }
                                    onValueChange={(value) =>
                                        setLocal({
                                            ...local,
                                            year: value === 'all' ? '' : value,
                                        })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder={
                                                allowAllYears
                                                    ? 'All years'
                                                    : 'Year'
                                            }
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {allowAllYears && (
                                            <SelectItem value="all">
                                                All years
                                            </SelectItem>
                                        )}
                                        {years.map((entry) => (
                                            <SelectItem
                                                key={entry.id}
                                                value={String(entry.year)}
                                            >
                                                {entry.year}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-2">
                                    <Label htmlFor="date_from">From</Label>
                                    <Input
                                        id="date_from"
                                        type="date"
                                        value={local.date_from}
                                        onChange={setField('date_from')}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="date_to">To</Label>
                                    <Input
                                        id="date_to"
                                        type="date"
                                        value={local.date_to}
                                        onChange={setField('date_to')}
                                    />
                                </div>
                            </div>
                            {fields.map(([key, label, optionKey]) => (
                                <div key={key} className="space-y-2">
                                    <Label htmlFor={key}>{label}</Label>
                                    <Input
                                        id={key}
                                        list={`${key}-list`}
                                        value={local[key] ?? ''}
                                        onChange={setField(key)}
                                        placeholder={label}
                                    />
                                    <datalist id={`${key}-list`}>
                                        {(filterOptions[optionKey] || []).map(
                                            (value) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                />
                                            ),
                                        )}
                                    </datalist>
                                </div>
                            ))}
                        </div>
                        <SheetFooter className="gap-2 border-t pt-4">
                            <Button type="button" variant="ghost" onClick={reset}>
                                Reset
                            </Button>
                            <Button type="submit">Apply filters</Button>
                        </SheetFooter>
                    </SheetContent>
                </Sheet>
                <Button type="submit">Search</Button>
            </div>

            {(local.search || activeChips.length > 0) && (
                <div className="flex flex-wrap items-center gap-2">
                    {local.search && (
                        <Badge variant="secondary" className="gap-1 pr-1 font-normal">
                            Search: {local.search}
                            <button
                                type="button"
                                onClick={() => removeChip('search')}
                                className="rounded-sm p-0.5 hover:bg-background/60"
                                aria-label="Remove search"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </Badge>
                    )}
                    {activeChips.map(([key, label]) => (
                        <Badge
                            key={key}
                            variant="secondary"
                            className="gap-1 pr-1 font-normal"
                        >
                            {label}: {local[key]}
                            <button
                                type="button"
                                onClick={() => removeChip(key)}
                                className="rounded-sm p-0.5 hover:bg-background/60"
                                aria-label={`Remove ${label}`}
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </Badge>
                    ))}
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-7 px-2 text-xs"
                        onClick={reset}
                    >
                        Clear all
                    </Button>
                </div>
            )}
        </form>
    );
}
