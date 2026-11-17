<?php
declare(strict_types=1);

namespace App\Services;

/** Dados do fornecedor e da conformidade (preenchidos no .env pelo dono da plataforma). */
final class Legal
{
    private const FIELDS = [
        'COMPANY_NAME' => 'nome ou razão social do fornecedor',
        'COMPANY_DOC' => 'CPF ou CNPJ',
        'COMPANY_ADDRESS' => 'endereço completo',
        'CONTACT_EMAIL' => 'e-mail de atendimento',
        'DPO_NAME' => 'nome do encarregado de dados (DPO)',
        'DPO_EMAIL' => 'e-mail do encarregado de dados (DPO)',
        'COPYRIGHT_EMAIL' => 'e-mail para notificações de direitos autorais',
        'LEGAL_FORO' => 'cidade/UF do foro',
        'HOSTING_INFO' => 'provedor e país dos servidores',
    ];

    public static function termsVersion(): string
    {
        return (string) env('TERMS_VERSION', '2026-11-17');
    }

    /** Valor do campo ou um marcador visível de pendência (nunca inventa dado do dono). */
    public static function get(string $key): string
    {
        $v = trim((string) env($key, ''));
        return $v !== '' ? e($v) : '<mark class="pending" title="Preencha ' . e($key) . ' no arquivo .env">[pendente: ' . e(self::FIELDS[$key] ?? $key) . ']</mark>';
    }

    public static function raw(string $key): string
    {
        return trim((string) env($key, ''));
    }

    /** Campos que faltam preencher no .env para publicar os termos. */
    public static function missing(): array
    {
        $out = [];
        foreach (self::FIELDS as $k => $label) {
            if (self::raw($k) === '') {
                $out[$k] = $label;
            }
        }
        return $out;
    }
}
