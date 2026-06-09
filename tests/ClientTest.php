<?php

/**
 * Copyright (c) 2019. CDEK-IT. All rights reserved.
 * See LICENSE.md for license details.
 *
 * @author Chizhekov Viktor
 */

namespace CdekSDK2\Tests;

use CdekSDK2\Actions\Intakes;
use CdekSDK2\Actions\Offices;
use CdekSDK2\Actions\Orders;
use CdekSDK2\Actions\Webhooks;
use CdekSDK2\BaseTypes\Invoice;
use CdekSDK2\Constants;
use CdekSDK2\Dto\OrderInfo;
use CdekSDK2\Dto\RegionList;
use CdekSDK2\Client;
use CdekSDK2\Dto\TariffList;
use CdekSDK2\Dto\TariffListItem;
use CdekSDK2\Exceptions\ParsingException;
use CdekSDK2\Exceptions\RequestException;
use CdekSDK2\Http\ApiResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

class ClientTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;
    protected function setUp(): void
    {
        parent::setUp();
        $psr18Client = new Psr18Client(HttpClient::create([
            'verify_peer' => false,
            'verify_host' => false,
        ]));
        $this->client = new Client($psr18Client);
        \Doctrine\Common\Annotations\AnnotationReader::addGlobalIgnoredName('phan');

        /** @phan-suppress-next-line PhanDeprecatedFunction */
        \Doctrine\Common\Annotations\AnnotationRegistry::registerLoader('class_exists');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->client = null;
    }

    public function testSetAccount()
    {
        $this->client->setAccount('newaccount');
        $this->assertStringContainsString('newaccount', $this->client->getAccount());
    }

    public function testGetAccount()
    {
        $this->client->setAccount('account');
        $this->assertStringContainsString('account', $this->client->getAccount());
    }

    public function testSetSecure()
    {
        $this->client->setSecure('newsecure');
        $this->assertStringContainsString('newsecure', $this->client->getSecure());
    }

    public function testIsTest()
    {
        $this->assertFalse($this->client->isTest());
    }

    public function testSetTest(): Client
    {
        $this->client->setTest(true);
        $this->assertTrue($this->client->isTest());
        $this->assertStringContainsString(Constants::TEST_ACCOUNT, $this->client->getAccount());
        $this->assertStringContainsString(Constants::TEST_SECURE, $this->client->getSecure());
        return $this->client;
    }

    public function testAuthorize()
    {
        $this->assertEmpty($this->client->getToken());
        $this->client->setTest(true);
        $this->client->authorize();
        $this->assertNotEmpty($this->client->getToken());
        $this->assertGreaterThan(time(), $this->client->getExpire());
    }

    /*
     * @covers \CdekSDK2\Client::getToken
     */
    public function testSetToken()
    {
        $this->client->setToken('qwerty');
        $this->assertStringContainsString('qwerty', $this->client->getToken());
    }

    public function testSetExpire()
    {
        $this->client->setExpire(1);
        $this->assertStringContainsString(1, $this->client->getExpire());
    }


    public function testIsExpired()
    {
        $this->assertTrue($this->client->isExpired());
    }


    public function testOrders()
    {
        $response = $this->client->orders();
        $this->assertInstanceOf(Orders::class, $response);
    }

    public function testOffices()
    {
        $response = $this->client->offices();
        $this->assertInstanceOf(Offices::class, $response);
    }

    public function testIntakes()
    {
        $response = $this->client->intakes();
        $this->assertInstanceOf(Intakes::class, $response);
    }

    public function testWebhooks()
    {
        $response = $this->client->webhooks();
        $this->assertInstanceOf(Webhooks::class, $response);
    }

    public function testFormatResponse()
    {
        $response = $this->createMock(ApiResponse::class);
        $response->method('getBody')
            ->willReturn('{"entity":{"uuid":"8bf0e8e2d724","orders":[{"order_uuid":"61a552583eb3"}],"copy_count":1,'
        . '"url":"","statuses":[{"code":"ACCEPTED","name":"Принят","date_time":"2020-02-11T17:52:45+0700"},'
        . '{"code":"PROCESSING","name":"Формируется","date_time":"2020-02-11T17:52:45+0700"}]},'
        . '"requests":[{"errors":[],"warnings":[]}]}');
        $response->method('isOk')->willReturn(true);
        $invoice_response = $this->client->formatResponse($response, Invoice::class);
        $this->assertInstanceOf(Invoice::class, $invoice_response->entity);
    }

    public function testFormatResponseTariff()
    {
        $response = $this->createMock(ApiResponse::class);
        $response->method('getBody')
            ->willReturn('{"delivery_sum":1315.0,"period_min":8,"period_max":12,"calendar_min":8,"calendar_max":12,
            "weight_calc":3000,"services":[{"code":"INSURANCE","sum":0.00}],"total_sum":1578.0,"currency":"RUB"}');
        $response->method('isOk')->willReturn(true);

        $tariff = $this->client->formatBaseResponse($response, \CdekSDK2\Dto\Tariff::class);
        $this->assertInstanceOf(\CdekSDK2\Dto\Tariff::class, $tariff);

        foreach ($tariff->services as $service) {
            $this->assertInstanceOf(\CdekSDK2\Dto\TariffService::class, $service);
        }
    }

    public function testFormatResponseException()
    {
        $this->expectException(ParsingException::class);
        $response = $this->createMock(ApiResponse::class);
        $response->method('getBody')
            ->willReturn('{"type":"ORDER_STATUS","uuid":"c7e28f79fe39","url":"my_url"}');
        $response->method('isOk')->willReturn(true);
        $this->client->formatResponse($response, 'SomeNotFoundClass');
    }

    public function testResponseContainsErrorException()
    {
        $this->expectException(RequestException::class);
        $response = $this->createMock(ApiResponse::class);
        $response->method('getErrors')
            ->willReturn([
                [
                    'code' => 'code',
                    'message' => 'message'
                ]
            ]);
        $this->client->formatResponse($response, OrderInfo::class);
    }

    public function testFormatResponseRegionList()
    {
        $response = $this->createMock(ApiResponse::class);
        $response->method('getBody')
            ->willReturn('[{"country_code":"DE","region":"Нижняя Саксония","region_code":"641","country":"Германия"}]');
        $response->method('isOk')->willReturn(true);
        $region_list = $this->client->formatResponseList($response, RegionList::class);
        $this->assertInstanceOf(RegionList::class, $region_list);
    }

    public function testFormatResponseTarifflist()
    {
        $response = $this->createMock(ApiResponse::class);
        $response->method('getBody')
            ->willReturn('{"tariff_codes":[{"tariff_code":62,"tariff_name":"Магистральный экспресс склад-склад",
            "tariff_description":"","delivery_mode":4,"delivery_sum":1710.0,"period_min":8,"period_max":12,
            "calendar_min":8,"calendar_max":12}]}');
        $response->method('isOk')->willReturn(true);
        $tariffList = $this->client->formatResponseList($response, TariffList::class);

        $this->assertInstanceOf(TariffList::class, $tariffList);

        foreach ($tariffList->tariff_codes as $tariff) {
            $this->assertInstanceOf(TariffListItem::class, $tariff);
        }
    }

    public function testFormatResponseListException()
    {
        $this->expectException(ParsingException::class);
        $response = $this->createMock(ApiResponse::class);
        $response->method('getBody')
            ->willReturn('[{"country_code":"DE","region":"Нижняя Саксония","region_code":"641","country":"Германия"}]');
        $response->method('isOk')->willReturn(true);
        $this->client->formatResponseList($response, 'AnotherClass');
    }

    public function testFormatResponseOrderInfo()
    {
        $response = $this->createMock(ApiResponse::class);
        $response->method('getBody')
            ->willReturn('{"entity":{"uuid":"00000000-0000-4000-8000-000000000001","type":1,"is_return":false,'
            . '"is_reverse":false,"cdek_number":"10000000001","number":"TEST-ORDER-001","tariff_code":136,'
            . '"delivery_point":"KUR58","items_cost_currency":"RUB","recipient_currency":"RUB",'
            . '"keep_free_until":"2026-06-14T18:59:59Z","delivery_recipient_cost":{"value":0.0,"vat_sum":0.0},'
            . '"sender":{"company":"Test Sender LLC","name":"Test Sender LLC",'
            . '"contragent_type":"LEGAL_ENTITY","phones":[{"number":"79000000001"}],'
            . '"passport_requirements_satisfied":false},"seller":{},'
            . '"recipient":{"company":"Test Recipient","name":"Test Recipient","email":"recipient@example.com",'
            . '"phones":[{"number":"79000000002"}],"passport_requirements_satisfied":false},'
            . '"from_location":{"code":44,"city_uuid":"11111111-1111-4111-8111-111111111111","city":"Москва",'
            . '"country_code":"RU","country":"Россия","region":"Москва","region_code":81},'
            . '"to_location":{"code":93,"city_uuid":"22222222-2222-4222-8222-222222222222","city":"Курган",'
            . '"country_code":"RU","country":"Россия","region":"Курганская область","region_code":28},'
            . '"services":[{"code":"INSURANCE","parameter":"0.00","sum":0.00,"total_sum":0.00,'
            . '"discount_percent":0,"discount_sum":0.00,"vat_rate":5.00,"vat_sum":0.00}],'
            . '"packages":[{"number":"TEST-ORDER-001","weight":130,"length":12,"width":12,"height":9,'
            . '"items":[{"name":"товарное вложение","ware_key":"GOODS","payment":{"value":0.0,"vat_sum":0.0},'
            . '"weight":130,"amount":1,"cost":0.0}]}],'
            . '"statuses":[{"code":"DELIVERED","name":"Вручен","date_time":"2026-06-07T10:13:53+0000",'
            . '"deleted":false}],"is_client_return":false,"delivery_mode":"4","has_reverse_order":false,'
            . '"planned_delivery_date":"2026-06-06","delivery_date":"2026-06-07",'
            . '"delivery_detail":{"date":"2026-06-07","recipient_name":"Test Recipient","payment_sum":0.00,'
            . '"delivery_sum":345.00,"total_sum":362.25,"payment_info":[],"delivery_vat_rate":5.00,'
            . '"delivery_vat_sum":17.25,"delivery_discount_percent":0,"delivery_discount_sum":0.00},'
            . '"calls":{}},"requests":[],"related_entities":[]}');
        $response->method('isOk')->willReturn(true);

        $orderResponse = $this->client->formatResponse($response, OrderInfo::class);

        $this->assertInstanceOf(OrderInfo::class, $orderResponse->entity);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $orderResponse->entity->uuid);
        $this->assertSame('2026-06-14T18:59:59Z', $orderResponse->entity->keep_free_until);
        $this->assertFalse($orderResponse->entity->is_client_return);
        $this->assertSame('4', $orderResponse->entity->delivery_mode);
        $this->assertSame('2026-06-06', $orderResponse->entity->planned_delivery_date);
        $this->assertSame('2026-06-07', $orderResponse->entity->delivery_date);
        $this->assertSame('LEGAL_ENTITY', $orderResponse->entity->sender->contragent_type);
        $this->assertFalse($orderResponse->entity->sender->passport_requirements_satisfied);
        $this->assertSame('Россия', $orderResponse->entity->from_location->country);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $orderResponse->entity->from_location->city_uuid);
        $this->assertSame(5.0, $orderResponse->entity->services[0]->vat_rate);
        $this->assertSame(17.25, $orderResponse->entity->delivery_detail->delivery_vat_sum);
        $this->assertSame([], $orderResponse->entity->delivery_detail->payment_info);
    }
}
