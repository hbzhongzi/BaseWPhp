<?php
declare(strict_types=1);

namespace app\common\exception;

use RuntimeException;

final class BusinessException extends RuntimeException
{
    private int $businessCode;
    private int $httpStatus;

    public function __construct(
        string $message,
        int $businessCode,
        int $httpStatus = 400
    ) {
        parent::__construct($message);
        $this->businessCode = $businessCode;
        $this->httpStatus = $httpStatus;
    }

    public function getBusinessCode(): int
    {
        return $this->businessCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
