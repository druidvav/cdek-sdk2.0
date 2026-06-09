<?php

declare(strict_types=1);

namespace CdekSDK2\Dto;

use JMS\Serializer\Annotation\Type;

class PaymentInfo
{
    /**
     * Тип оплаты (CARD — картой, CASH — наличными)
     * @Type("string")
     * @var string
     */
    public $type;

    /**
     * Сумма оплаты
     * @Type("float")
     * @var float
     */
    public $sum;
}
