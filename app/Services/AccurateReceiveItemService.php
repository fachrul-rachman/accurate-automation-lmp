<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class AccurateReceiveItemService
{
    /**
     * @return array{
     *     accurate_receive_item_id: string|null,
     *     number: string,
     *     po_number: string|null,
     *     supplier_name: string|null,
     *     pb_date: string|null,
     *     items: array<int, array{accurate_item_row_id: string|null, item_no: string|null, item_name: string|null, po_number: string|null, quantity: float|null, unit: string|null}>,
     *     raw: array<string, mixed>
     * }
     */
    public function findReceiveItemDetail(Location $location, string $pbNumber): array
    {
        $host = $this->locationHost($location);
        $normalizedPbNumber = trim($pbNumber);
        $headers = $this->headers($location);
        $receiveItem = $this->searchReceiveItem($host, $headers, $normalizedPbNumber);
        $detail = $this->receiveItemDetail($host, $headers, (string) $receiveItem['id']);
        $items = $this->detailItems($detail);

        if ($items === []) {
            throw new RuntimeException('PB ditemukan tetapi tidak memiliki item. PB tidak dapat diproses.');
        }

        $mappedItems = $this->mappedItems($items);

        return [
            'accurate_receive_item_id' => isset($detail['id']) ? (string) $detail['id'] : null,
            'number' => $this->stringValue($detail, 'number') ?? $normalizedPbNumber,
            'po_number' => $this->purchaseOrderNumber($detail) ?? $this->firstItemPurchaseOrderNumber($mappedItems),
            'supplier_name' => $this->nestedStringValue($detail, ['vendor', 'name']),
            'pb_date' => $this->dateStringValue($detail, 'transDateView'),
            'items' => $mappedItems,
            'raw' => $detail,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function searchReceiveItem(string $host, array $headers, string $pbNumber): array
    {
        $response = Http::timeout($this->timeout())
            ->withHeaders($headers)
            ->get($this->accurateUrl($host, '/api/receive-item/list.do'), [
                'filter.number' => $pbNumber,
            ]);

        $rows = $this->responseData($response, 'Gagal mencari PB di Accurate.');

        if ($rows === []) {
            throw new RuntimeException('Nomor PB tidak ditemukan di Accurate.');
        }

        foreach ($rows as $row) {
            if (is_array($row) && ($row['number'] ?? null) === $pbNumber) {
                return $this->objectData($row, 'Nomor PB tidak ditemukan di Accurate.');
            }
        }

        $firstRow = $rows[0] ?? null;

        if (! is_array($firstRow)) {
            throw new RuntimeException('Nomor PB tidak ditemukan di Accurate.');
        }

        return $this->objectData($firstRow, 'Nomor PB tidak ditemukan di Accurate.');
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function receiveItemDetail(string $host, array $headers, string $receiveItemId): array
    {
        $response = Http::timeout($this->timeout())
            ->withHeaders($headers)
            ->get($this->accurateUrl($host, '/api/receive-item/detail.do'), [
                'id' => $receiveItemId,
            ]);

        return $this->responseObjectData($response, 'Gagal mengambil detail PB dari Accurate.');
    }

    private function locationHost(Location $location): string
    {
        $host = $location->accurate_host_url;

        if (! is_string($host) || trim($host) === '') {
            throw new RuntimeException('Lokasi belum memiliki Accurate URL.');
        }

        return $host;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function responseData(Response $response, string $message): array
    {
        if (! $response->successful()) {
            throw new RuntimeException($message);
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['s'] ?? false) !== true || ! array_key_exists('d', $payload)) {
            throw new RuntimeException($message);
        }

        $data = $payload['d'];

        if (! is_array($data)) {
            throw new RuntimeException($message);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseObjectData(Response $response, string $message): array
    {
        return $this->objectData($this->responseData($response, $message), $message);
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return array<string, mixed>
     */
    private function objectData(array $data, string $message): array
    {
        $objectData = [];

        foreach ($data as $key => $value) {
            if (! is_string($key)) {
                throw new RuntimeException($message);
            }

            $objectData[$key] = $value;
        }

        return $objectData;
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<int, mixed>
     */
    private function detailItems(array $detail): array
    {
        $items = $detail['detailItem'] ?? null;

        return is_array($items) ? $items : [];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array{accurate_item_row_id: string|null, item_no: string|null, item_name: string|null, po_number: string|null, quantity: float|null, unit: string|null}>
     */
    private function mappedItems(array $items): array
    {
        $mappedItems = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $mappedItems[] = [
                'accurate_item_row_id' => isset($item['id']) ? (string) $item['id'] : null,
                'item_no' => $this->nestedStringValue($item, ['item', 'no']),
                'item_name' => $this->stringValue($item, 'detailName') ?? $this->nestedStringValue($item, ['item', 'name']),
                'po_number' => $this->purchaseOrderNumber($this->objectData($item, 'Gagal membaca item PB dari Accurate.')),
                'quantity' => $this->floatValue($item, 'quantity'),
                'unit' => $this->nestedStringValue($item, ['itemUnit', 'name']),
            ];
        }

        return $mappedItems;
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function purchaseOrderNumber(array $detail): ?string
    {
        return $this->stringValue($detail, 'poNumber')
            ?? $this->nestedStringValue($detail, ['purchaseOrder', 'number'])
            ?? $this->nestedStringValue($detail, ['purchaseOrder', 'no'])
            ?? $this->nestedStringValue($detail, ['purchaseOrder', 'code'])
            ?? $this->nestedStringValue($detail, ['purchaseOrderDetail', 'purchaseOrder', 'number'])
            ?? $this->nestedStringValue($detail, ['purchaseOrderDetail', 'purchaseOrder', 'no'])
            ?? $this->nestedStringValue($detail, ['po', 'number'])
            ?? $this->nestedStringValue($detail, ['order', 'number'])
            ?? $this->stringValue($detail, 'purchaseOrderNumber')
            ?? $this->stringValue($detail, 'purchaseOrderNo')
            ?? $this->stringValue($detail, 'poNo')
            ?? $this->stringValue($detail, 'orderNumber');
    }

    /**
     * @param  array<int, array{po_number: string|null}>  $items
     */
    private function firstItemPurchaseOrderNumber(array $items): ?string
    {
        foreach ($items as $item) {
            if ($item['po_number'] !== null) {
                return $item['po_number'];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function headers(Location $location): array
    {
        [$token, $secret] = $this->credentials($location);
        $timestamp = (string) time();

        return [
            'Authorization' => "Bearer {$token}",
            'X-Api-Timestamp' => $timestamp,
            'X-Api-Signature' => hash_hmac('sha256', $timestamp, $secret),
            'Accept' => 'application/json',
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function credentials(Location $location): array
    {
        $locationKey = Str::slug($location->name, '_');
        $token = config("services.accurate.locations.{$locationKey}.api_token");
        $secret = config("services.accurate.locations.{$locationKey}.signature_secret");

        if (is_string($token) && trim($token) !== '' && is_string($secret) && trim($secret) !== '') {
            return [$token, $secret];
        }

        $fallbackToken = config('services.accurate.api_token');
        $fallbackSecret = config('services.accurate.signature_secret');

        if (is_string($fallbackToken) && trim($fallbackToken) !== '' && is_string($fallbackSecret) && trim($fallbackSecret) !== '') {
            return [$fallbackToken, $fallbackSecret];
        }

        throw new RuntimeException("Konfigurasi Accurate untuk lokasi {$location->name} belum lengkap.");
    }

    private function timeout(): int
    {
        $timeout = config('services.accurate.timeout', 15);

        return is_numeric($timeout) ? (int) $timeout : 15;
    }

    private function accurateUrl(string $host, string $path): string
    {
        return rtrim($host, '/').'/accurate'.$path;
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function stringValue(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int|string, mixed>  $data
     */
    private function nestedStringValue(array $data, array $keys): ?string
    {
        $value = $data;

        foreach ($keys as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dateStringValue(array $data, string $key): ?string
    {
        $value = $this->stringValue($data, $key);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function floatValue(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }
}
