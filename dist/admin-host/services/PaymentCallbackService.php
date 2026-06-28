<?php

require_once SERVICE_PATH . '/BackendApiClient.php';
require_once REPOSITORY_PATH . '/UserRepository.php';
require_once REPOSITORY_PATH . '/DepositRepository.php';

/**
 * Ödeme/çekim callback iş mantığı (api/index.php kaynağı).
 */
class PaymentCallbackService
{
    private UserRepository $userRepo;
    private DepositRepository $depositRepo;

    public function __construct()
    {
        $this->userRepo    = new UserRepository(BackendApiClient::SVC_PAYMENT_CALLBACK);
        $this->depositRepo = new DepositRepository(BackendApiClient::SVC_PAYMENT_CALLBACK);
    }

    public function handleWithdrawalReturn(array $data): array
    {
        $status = $data['Status'] ?? '';
        if ($status !== 'onay' && $status !== 'ret') {
            return ['status' => 'error', 'message' => 'Geçersiz durum', 'http' => 400];
        }
        return ['status' => 'ok', 'http' => 200];
    }

    public function handleDepositResult(array $data): array
    {
        $durum = $data['durum'] ?? '';
        if ($durum !== 'onay' && $durum !== 'ret') {
            return ['status' => 'error', 'message' => 'Geçersiz durum', 'http' => 400];
        }

        $kullanici_id = (int) ($data['kullanici_id'] ?? 0);
        $tutar        = (float) ($data['tutar'] ?? 0);
        $db_durum     = $durum === 'onay' ? 0 : 1;
        $status       = $durum === 'onay' ? 'onay' : 'red';

        $user = $this->userRepo->findById($kullanici_id);
        if (!$user) {
            return ['status' => 'error', 'message' => 'Kullanıcı bulunamadı', 'http' => 404];
        }

        if ($db_durum === 0) {
            $this->userRepo->updateBalance($kullanici_id, $tutar);
            $db_durum = 2;
        }

        $this->depositRepo->insert(
            $data['id'] ?? null,
            $kullanici_id,
            $tutar,
            $data['yontem'] ?? '',
            $data['referans'] ?? null,
            $data['tarih'] ?? null,
            $db_durum,
            $status,
            $data['token'] ?? null,
            $data['kullanici_isim'] ?? null
        );

        return ['status' => true, 'http' => 200];
    }
}
