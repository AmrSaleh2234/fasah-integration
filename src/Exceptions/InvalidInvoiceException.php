<?php

namespace AmrSaleh\FasahPay\Exceptions;

class InvalidInvoiceException extends FasahPayException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid Fasah invoice: '.implode('; ', $errors));
    }
}
