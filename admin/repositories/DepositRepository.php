<?php

require_once dirname(__DIR__) . '/services/BackendApiClient.php';

/**
 * Para yatırma kaydı – ödeme callback backend API.
 */
class DepositRepository
{
    private string $backendKey;

    public function __construct(string $backendKey = BackendApiClient::SVC_PAYMENT_CALLBACK)
    {
        $this->backendKey = $backendKey;
    }

    /**
     * Yanıt kontrol edilmezse kayıt yazılmadığı halde callback başarılı
     * sanılır — bu yüzden bool döner. Backend'de bu path yoksa false döner
     * ve çağıran taraf ödeme sağlayıcısına hata iletir.
     */
    public function insert(
        ?string $user_id,
        int $uye,
        float $miktar,
        string $tur,
        ?string $referans,
        ?string $tarih,
        int $durum,
        string $aciklama,
        ?string $token,
        ?string $adsoyad
    ): bool {
        $response = BackendApiClient::request('POST', $this->backendKey, '/deposits/parayatir', [], [
            'user_id'  => $user_id,
            'uye'      => $uye,
            'miktar'   => $miktar,
            'tur'      => $tur,
            'referans' => $referans,
            'tarih'    => $tarih,
            'durum'    => $durum,
            'aciklama' => $aciklama,
            'token'    => $token,
            'adsoyad'  => $adsoyad,
        ]);

        return is_array($response) && (bool) ($response['success'] ?? false);
    }
}
