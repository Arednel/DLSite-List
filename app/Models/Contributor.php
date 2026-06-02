<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contributor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_key',
        'maker_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (Contributor $contributor): void {
            if ($contributor->isDirty('name') || blank($contributor->name_key)) {
                $contributor->name_key = self::nameKey($contributor->name);
            }
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public static function resolveByName(string $name, ?string $makerId = null): self
    {
        $normalizedName = self::normalizeName($name);

        if ($normalizedName === null) {
            throw new \InvalidArgumentException('Contributor name must not be empty.');
        }

        $contributor = self::query()->firstOrCreate(
            ['name_key' => self::nameKey($normalizedName)],
            [
                'name' => $normalizedName,
                'maker_id' => $makerId,
            ],
        );

        if ($makerId !== null && $contributor->maker_id === null) {
            $contributor->forceFill(['maker_id' => $makerId])->save();
        }

        return $contributor;
    }

    /**
     * @return list<int>
     */
    public static function resolveIdsFromNames(array $names, ?string $makerId = null): array
    {
        return collect($names)
            ->map(fn(mixed $name): ?string => self::normalizeName($name))
            ->filter()
            ->unique(fn(string $name): string => self::nameKey($name))
            ->map(fn(string $name): int => (int) self::resolveByName($name, $makerId)->getKey())
            ->values()
            ->all();
    }

    public static function nameKey(mixed $name): string
    {
        return mb_convert_case(trim((string) $name), MB_CASE_FOLD, 'UTF-8');
    }

    private static function normalizeName(mixed $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $normalizedName = trim((string) $name);

        return $normalizedName === '' ? null : $normalizedName;
    }
}
