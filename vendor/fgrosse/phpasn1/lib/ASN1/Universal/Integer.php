<?php
/*
 * This file is part of the PHPASN1 library.
 *
 * Copyright © Friedrich Große <friedrich.grosse@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FG\ASN1\Universal;

use Exception;
use FG\ASN1\Exception\ParserException;
use FG\ASN1\ASNObject;
use FG\ASN1\Parsable;
use FG\ASN1\Identifier;

class Integer extends ASNObject implements Parsable
{
    /** @var int */
    private $value;

    /**
     * @param int $value
     *
     * @throws Exception if the value is not numeric
     */
    public function __construct($value)
    {
        if (is_numeric($value) == false) {
            throw new Exception("Invalid VALUE [{$value}] for ASN1_INTEGER");
        }
        $this->value = $value;
    }

    public function getType()
    {
        return Identifier::INTEGER;
    }

    public function getContent()
    {
        return $this->value;
    }

    protected function calculateContentLength()
    {
        return strlen($this->getEncodedValue());
    }

    /**
     * @param resource|\GMP $number
     * @param int $positions
     *
     * @return resource|\GMP
     */
    private function rightShift($number, $positions)
    {
        // Shift 1 right = div / 2
        return gmp_div($number, gmp_pow(2, (int) $positions));
    }

    protected function getEncodedValue()
    {
        $value = gmp_init($this->value, 10);
        $negative = gmp_cmp($value, 0) < 0;
        if ($negative) {
             $value = gmp_abs($value);
             $limit = 0x80;
        } else {
             $limit = 0x7f;
        }

        $mod = 0xff+1;
        $values = [];
        while(gmp_cmp($value, $limit) > 0) {
            $values[] = (int) gmp_strval(gmp_mod($value, $mod), 10);
            $value = $this->rightShift($value, 8);
        }

        $values[] = (int) gmp_strval(gmp_mod($value, $mod), 10);
        $numValues = count($values);

        if ($negative) {
            for ($i = 0; $i < $numValues; $i++) {
                $values[$i] = 0xff - $values[$i];
            }
            for ($i = 0; $i < $numValues; $i++) {
                $values[$i] += 1;
                if ($values[$i] <= 0xff) {
                    break;
                }
                assert($i != $numValues - 1);
                $values[$i] = 0;
            }
            if ($values[$numValues - 1] == 0x7f) {
                $values[] = 0xff;
            }
        }
        $values = array_reverse($values);
        $r = pack("C*", ...$values);
        return $r;
    }

    private static function ensureMinimalEncoding($binaryData, $offsetIndex)
    {
        // All the first nine bits cannot equal 0 or 1, which would
        // be non-minimal encoding for positive and negative integers respectively
        if ((ord($binaryData[$offsetIndex]) == 0x00 && (ord($binaryData[$offsetIndex+1]) & 0x80) == 0) ||
            (ord($binaryData[$offsetIndex]) == 0xff && (ord($binaryData[$offsetIndex+1]) & 0x80) == 0x80)) {
            throw new ParserException("Integer not minimally encoded", $offsetIndex);
        }
    }

    public static function fromBinary(&$binaryData, &$offsetIndex = 0)
    {
        $parsedObject = new static(0);
        self::parseIdentifier($binaryData[$offsetIndex], $parsedObject->getType(), $offsetIndex++);
        $contentLength = self::parseContentLength($binaryData, $offsetIndex, 1);

        if ($contentLength > 1) {
            self::ensureMinimalEncoding($binaryData, $offsetIndex);
        }
        $isNegative = (ord($binaryData[$offsetIndex]) & 0x80) != 0x00;
        $number = gmp_init(ord($binaryData[$offsetIndex++]) & 0x7F, 10);
        
        for ($i = 0; $i < $contentLength - 1; $i++) {
            $number = gmp_or(gmp_mul($number, 0x100), ord($binaryData[$offsetIndex++]));
        }

        if ($isNegative) {
            $number = gmp_sub($number, gmp_pow(2, 8 * $contentLength - 1));
        }

        $parsedObject = new static(gmp_strval($number, 10));
        $parsedObject->setContentLength($contentLength);

        return $parsedObject;
    }
}
