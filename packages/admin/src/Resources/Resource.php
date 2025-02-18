<?php

namespace Filament\Resources;

use Closure;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class Resource
{
    protected static ?string $breadcrumb = null;

    protected static bool $isGloballySearchable = true;

    protected static ?string $label = null;

    protected static ?string $model = null;

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = null;

    protected static ?string $pluralLabel = null;

    protected static ?string $recordTitleAttribute = null;

    protected static ?string $slug = null;

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function registerNavigationItems(): void
    {
        if (! static::canViewAny()) {
            return;
        }

        $routeBaseName = static::getRouteBaseName();

        Filament::registerNavigationItems([
            NavigationItem::make()
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn () => request()->routeIs("{$routeBaseName}*"))
                ->label(static::getNavigationLabel())
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function resolveRecordRouteBinding($key): ?Model
    {
        $model = static::getModel();

        return static::getEloquentQuery()
            ->where((new $model())->getRouteKeyName(), $key)
            ->first();
    }

    public static function can(string $action, ?Model $record = null): bool
    {
        $policy = Gate::getPolicyFor($model = static::getModel());

        if ($policy === null || (! method_exists($policy, $action))) {
            return true;
        }

        return Gate::check($action, $record ?? $model);
    }

    public static function canViewAny(): bool
    {
        return static::can('viewAny');
    }

    public static function canCreate(): bool
    {
        return static::hasPage('create') && static::can('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::hasPage('edit') && static::can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::can('delete', $record);
    }

    public static function canDeleteAny(): bool
    {
        return static::can('deleteAny');
    }

    public static function canGloballySearch(): bool
    {
        return static::$isGloballySearchable && count(static::getGloballySearchableAttributes()) && static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return static::hasPage('view') && static::can('view', $record);
    }

    public static function getBreadcrumb(): string
    {
        return static::$breadcrumb ?? Str::title(static::getPluralLabel());
    }

    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query();
    }

    public static function getGloballySearchableAttributes(): array
    {
        $titleAttribute = static::getRecordTitleAttribute();

        if ($titleAttribute === null) {
            return [];
        }

        return [$titleAttribute];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return static::getRecordTitle($record);
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        if (static::canEdit($record)) {
            return static::getUrl('edit', ['record' => $record]);
        }

        if (static::canView($record)) {
            return static::getUrl('view', ['record' => $record]);
        }

        return null;
    }

    public static function getGlobalSearchResults(string $searchQuery): Collection
    {
        $query = static::getGlobalSearchEloquentQuery();

        foreach (explode(' ', $searchQuery) as $searchQueryWord) {
            $query->where(function (Builder $query) use ($searchQueryWord) {
                $isFirst = true;

                foreach (static::getGloballySearchableAttributes() as $attributes) {
                    static::applyGlobalSearchAttributeConstraint($query, Arr::wrap($attributes), $searchQueryWord, $isFirst);
                }
            });
        }

        return $query
            ->get()
            ->map(fn (Model $record): array => [
                'details' => static::getGlobalSearchResultDetails($record),
                'title' => static::getGlobalSearchResultTitle($record),
                'url' => static::getGlobalSearchResultUrl($record),
            ]);
    }

    public static function getLabel(): string
    {
        return static::$label ?? (string) Str::of(class_basename(static::getModel()))
            ->kebab()
            ->replace('-', ' ');
    }

    public static function getModel(): string
    {
        return static::$model ?? (string) Str::of(class_basename(static::class))
            ->beforeLast('Resource')
            ->prepend('App\\Models\\');
    }

    public static function getPages(): array
    {
        return [];
    }

    public static function getPluralLabel(): string
    {
        return static::$pluralLabel ?? Str::plural(static::getLabel());
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return static::$recordTitleAttribute;
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record?->getAttribute(static::getRecordTitleAttribute()) ?? $record?->getKey();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getRouteBaseName(): string
    {
        $slug = static::getSlug();

        return "filament.resources.{$slug}";
    }

    public static function getRoutes(): Closure
    {
        return function () {
            $slug = static::getSlug();

            Route::name("{$slug}.")->prefix($slug)->group(function () use ($slug) {
                foreach (static::getPages() as $name => $page) {
                    Route::get($page['route'], $page['class'])->name($name);
                }
            });
        };
    }

    public static function getSlug(): string
    {
        return static::$slug ?? (string) Str::of(class_basename(static::getModel()))
            ->plural()
            ->kebab();
    }

    public static function getUrl($name = 'index', $params = []): string
    {
        $routeBaseName = static::getRouteBaseName();

        return route("{$routeBaseName}.{$name}", $params);
    }

    public static function hasPage($page): bool
    {
        return array_key_exists($page, static::getPages());
    }

    public static function hasRecordTitle(): bool
    {
        return static::getRecordTitleAttribute() !== null;
    }

    protected static function applyGlobalSearchAttributeConstraint(Builder $query, array $searchAttributes, string $searchQuery, bool &$isFirst): Builder
    {
        $searchOperator = match ($query->getConnection()->getDriverName()) {
            'pgsql' => 'ilike',
            default => 'like',
        };

        foreach ($searchAttributes as $searchAttribute) {
            $whereClause = $isFirst ? 'where' : 'orWhere';

            $query->when(
                Str::of($searchAttribute)->contains('.'),
                fn ($query) => $query->{"{$whereClause}Relation"}(
                    (string) Str::of($searchAttribute)->beforeLast('.'),
                    (string) Str::of($searchAttribute)->afterLast('.'),
                    $searchOperator,
                    "%{$searchQuery}%",
                ),
                fn ($query) => $query->{$whereClause}(
                    $searchAttribute,
                    $searchOperator,
                    "%{$searchQuery}%",
                ),
            );

            $isFirst = false;
        }

        return $query;
    }

    protected static function getGlobalSearchEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    protected static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup;
    }

    protected static function getNavigationIcon(): string
    {
        return static::$navigationIcon ?? 'heroicon-o-collection';
    }

    protected static function getNavigationLabel(): string
    {
        return static::$navigationLabel ?? Str::title(static::getPluralLabel());
    }

    protected static function getNavigationSort(): ?int
    {
        return static::$navigationSort;
    }

    protected static function getNavigationUrl(): string
    {
        return static::getUrl();
    }
}
