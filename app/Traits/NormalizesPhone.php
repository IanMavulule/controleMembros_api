<?php

namespace App\Traits;

trait NormalizesPhone
{
    /**
     * Formato aceite: +258 seguido de 9 dígitos (ex.: +258843799864).
     */
    public const PHONE_REGEX = '/^\+258\d{9}$/';

    /**
     * Converte variações comuns para o formato +258XXXXXXXXX:
     * "84 379 9864", "258843799864", "00258843799864" → "+258843799864".
     * Valores que não correspondam a nenhuma variação são devolvidos sem espaços,
     * para que a validação os rejeite.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $clean = preg_replace('/[\s\-().]/', '', $phone);

        return match (true) {
            (bool) preg_match('/^\+258\d{9}$/', $clean) => $clean,
            (bool) preg_match('/^00258(\d{9})$/', $clean, $m) => '+258'.$m[1],
            (bool) preg_match('/^258(\d{9})$/', $clean, $m) => '+258'.$m[1],
            (bool) preg_match('/^(\d{9})$/', $clean, $m) => '+258'.$m[1],
            default => $clean,
        };
    }

    /**
     * Normaliza o campo "phone" do pedido antes da validação (para FormRequests).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $this->merge(['phone' => self::normalizePhone($this->input('phone'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function phoneMessages(): array
    {
        return [
            'phone.regex' => 'O número de celular deve estar no formato +258XXXXXXXXX.',
            'phone.unique' => 'Este número de celular já está registado.',
        ];
    }
}
