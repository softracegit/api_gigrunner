<?php

namespace App\Services;

use App\Models\CreditBalance;
use App\Models\CreditTransaction;
use App\Models\License;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CreditService
{
    public function balances(User $user): array
    {
        $types = array_keys(config('credits.types', []));
        $rows = $user->creditBalances()->get()->keyBy('type');

        $out = [];
        foreach ($types as $type) {
            $out[$type] = (int) ($rows[$type]->balance ?? 0);
        }

        // Include any extra types the user may already have
        foreach ($rows as $type => $row) {
            if (! array_key_exists($type, $out)) {
                $out[$type] = (int) $row->balance;
            }
        }

        return $out;
    }

    public function balance(User $user, string $type): int
    {
        return (int) ($user->creditBalances()->where('type', $type)->value('balance') ?? 0);
    }

    public function credit(
        User $user,
        string $type,
        int $amount,
        string $reason,
        ?string $reference = null,
        ?array $meta = null,
    ): CreditBalance {
        if ($amount <= 0) {
            throw new InvalidArgumentException('O montante a creditar tem de ser positivo.');
        }

        $this->assertType($type);

        return DB::transaction(function () use ($user, $type, $amount, $reason, $reference, $meta) {
            $balance = CreditBalance::query()->firstOrCreate(
                ['user_id' => $user->id, 'type' => $type],
                ['balance' => 0],
            );

            $balance = CreditBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
            $balance->balance += $amount;
            $balance->save();

            CreditTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $balance->balance,
                'reason' => $reason,
                'reference' => $reference,
                'meta' => $meta,
            ]);

            return $balance;
        });
    }

    public function consume(
        User $user,
        string $type,
        int $amount,
        string $reason = 'consume',
        ?string $reference = null,
        ?array $meta = null,
    ): CreditBalance {
        if ($amount <= 0) {
            throw new InvalidArgumentException('O montante a consumir tem de ser positivo.');
        }

        $this->assertType($type);

        return DB::transaction(function () use ($user, $type, $amount, $reason, $reference, $meta) {
            $balance = CreditBalance::query()->firstOrCreate(
                ['user_id' => $user->id, 'type' => $type],
                ['balance' => 0],
            );

            $balance = CreditBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();

            if ($balance->balance < $amount) {
                throw new RuntimeException('Créditos insuficientes.');
            }

            $balance->balance -= $amount;
            $balance->save();

            CreditTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => -$amount,
                'balance_after' => $balance->balance,
                'reason' => $reason,
                'reference' => $reference,
                'meta' => $meta,
            ]);

            return $balance;
        });
    }

    /**
     * Simulate buying a pack (until real payments exist).
     *
     * @return array{pack: array, license: ?array, credits: array}
     */
    public function purchasePack(User $user, string $packKey): array
    {
        $pack = config("credits.packs.{$packKey}");

        if (! is_array($pack)) {
            throw new InvalidArgumentException('Pack inválido.');
        }

        return DB::transaction(function () use ($user, $packKey, $pack) {
            $licensePayload = null;

            if (! empty($pack['grants_license'])) {
                $grant = $pack['grants_license'];
                $days = $grant['days'] ?? null;

                $license = $user->licenses()->create([
                    'plan' => $grant['plan'] ?? 'standard',
                    'status' => License::STATUS_ACTIVE,
                    'starts_at' => now(),
                    'expires_at' => $days ? now()->addDays((int) $days) : null,
                ]);

                $licensePayload = $license->toApiArray();
            }

            foreach ($pack['credits'] ?? [] as $type => $amount) {
                $this->credit(
                    $user,
                    (string) $type,
                    (int) $amount,
                    'purchase',
                    $packKey,
                    [
                        'price_cents' => $pack['price_cents'] ?? null,
                        'currency' => $pack['currency'] ?? null,
                        'pack_name' => $pack['name'] ?? $packKey,
                    ],
                );
            }

            return [
                'pack' => [
                    'key' => $packKey,
                    'name' => $pack['name'] ?? $packKey,
                    'price_cents' => $pack['price_cents'] ?? 0,
                    'currency' => $pack['currency'] ?? 'EUR',
                ],
                'license' => $licensePayload,
                'credits' => $this->balances($user->fresh()),
            ];
        });
    }

    public function listPacks(): array
    {
        $packs = [];
        foreach (config('credits.packs', []) as $key => $pack) {
            $packs[] = [
                'key' => $key,
                'name' => $pack['name'],
                'description' => $pack['description'] ?? null,
                'price_cents' => $pack['price_cents'],
                'currency' => $pack['currency'] ?? 'EUR',
                'grants_license' => $pack['grants_license'] ?? null,
                'credits' => $pack['credits'] ?? [],
            ];
        }

        return $packs;
    }

    private function assertType(string $type): void
    {
        $known = array_keys(config('credits.types', []));

        if ($known !== [] && ! in_array($type, $known, true)) {
            throw new InvalidArgumentException("Tipo de crédito desconhecido: {$type}");
        }
    }
}
