<?php

declare(strict_types=1);

namespace CdekSDK2\BaseTypes;

use JMS\Serializer\Annotation\Type;

/**
 * Class Services
 * @package CdekSDK2\BaseTypes
 */
class Services extends Base
{
    /**
     * Код дополнительной услуги
     * @Type("string")
     * @var string
     */
    public $code;

    /**
     * Параметр дополнительной услуги
     * @Type("float")
     * @var float
     */
    public $parameter;

    /**
     * Сумма услуги (в валюте договора)
     * @Type("float")
     * @var float
     */
    public $sum;

    /**
     * Итоговая сумма услуги
     * @Type("float")
     * @var float
     */
    public $total_sum;

    /**
     * Процент скидки
     * @Type("float")
     * @var float
     */
    public $discount_percent;

    /**
     * Сумма скидки
     * @Type("float")
     * @var float
     */
    public $discount_sum;

    /**
     * Ставка НДС
     * @Type("float")
     * @var float
     */
    public $vat_rate;

    /**
     * Сумма НДС
     * @Type("float")
     * @var float
     */
    public $vat_sum;

    public function __construct(array $param = [])
    {
        parent::__construct($param);
        $this->rules = [
            'code' => 'required|alpha',
            'parameter' => 'numeric',
        ];
    }
}
