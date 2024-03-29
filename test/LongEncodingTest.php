<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
use PHPUnit\Framework\TestCase;

require_once('test_helper.php');

/**
 * Class LongEncodingTest
 */
class LongEncodingTest extends TestCase
{

  protected function setUp(): void
  {
    Avro::check_platform();
  }

  /**
   * @return bool
   */
  static function is_64_bit() { return (PHP_INT_SIZE == 8); }
  function skip_64_bit_test_on_32_bit()
  {
    if (!self::is_64_bit())
      $this->markTestSkipped('Requires 64-bit platform');
  }

  function skip_if_no_gmp()
  {
    if (!extension_loaded('gmp'))
      $this->markTestSkipped('Requires GMP PHP Extension.');
  }

  /**
   * @param $expected
   * @param $actual
   * @param $shift_type
   * @param $expected_binary
   * @param $actual_binary
   */
  function assert_bit_shift($expected, $actual, $shift_type,
                            $expected_binary, $actual_binary)
  {
    $this->assertEquals(
      $expected, $actual,
      sprintf("%s\nexpected: %d\n  actual: %d\nexpected b: %s\n  actual b: %s",
              $shift_type, $expected, $actual,
              $expected_binary, $actual_binary));
  }

  /**
   * @dataProvider bit_shift_provider
   * @param $val
   * @param $shift
   * @param $expected_lval
   * @param $expected_rval
   * @param $lbin
   * @param $rbin
   */
  function test_bit_shift($val, $shift, $expected_lval, $expected_rval, $lbin, $rbin)
  {

    $this->skip_64_bit_test_on_32_bit();

    $lval = (int) ((int) $val << $shift);
    $this->assert_bit_shift($expected_lval, strval($lval),
                            'lshift', $lbin, decbin($lval));
    $rval = ((int) $val >> $shift);
    $this->assert_bit_shift($expected_rval, strval($rval),
                            'rshift', $rbin, decbin($rval));
  }

  /**
   * @dataProvider bit_shift_provider
   * @param $val
   * @param $shift
   * @param $expected_lval
   * @param $expected_rval
   * @param $lbin
   * @param $rbin
   */
  function test_left_shift_gmp($val, $shift,
                               $expected_lval, $expected_rval,
                               $lbin, $rbin)
  {
    $this->skip_if_no_gmp();
    $lval = gmp_strval(AvroGMP::shift_left($val, $shift));
    $this->assert_bit_shift($expected_lval, $lval, 'gmp left shift',
                            $lbin, decbin((int) $lval));
  }

  /**
   * @dataProvider bit_shift_provider
   * @param $val
   * @param $shift
   * @param $expected_lval
   * @param $expected_rval
   * @param $lbin
   * @param $rbin
   */
  function test_right_shift_gmp($val, $shift, $expected_lval, $expected_rval,
                                $lbin, $rbin)
  {
    $this->skip_if_no_gmp();
    $rval = gmp_strval(AvroGMP::shift_right($val, $shift));
    $this->assert_bit_shift($expected_rval, $rval, 'gmp right shift',
                            $rbin, decbin((int) $rval));
  }

  /**
   * @dataProvider long_provider
   * @param $val
   * @param $expected_bytes
   */
  function test_encode_long($val, $expected_bytes)
  {
    $this->skip_64_bit_test_on_32_bit();
    $bytes = AvroIOBinaryEncoder::encode_long($val);
    $this->assertEquals($expected_bytes, $bytes);
  }

  /**
   * @dataProvider long_provider
   * @param $val
   * @param $expected_bytes
   */
  function test_gmp_encode_long($val, $expected_bytes)
  {
    $this->skip_if_no_gmp();
    $bytes = AvroGMP::encode_long($val);
    $this->assertEquals($expected_bytes, $bytes);
  }

  /**
   * @dataProvider long_provider
   * @param $expected_val
   * @param $bytes
   */
  function test_decode_long_from_array($expected_val, $bytes)
  {
    $this->skip_64_bit_test_on_32_bit();
    $ary = array_map('ord', str_split($bytes));
    $val = AvroIOBinaryDecoder::decode_long_from_array($ary);
    $this->assertEquals($expected_val, $val);
  }

  /**
   * @dataProvider long_provider
   * @param $expected_val
   * @param $bytes
   */
  function test_gmp_decode_long_from_array($expected_val, $bytes)
  {
    $this->skip_if_no_gmp();
    $ary = array_map('ord', str_split($bytes));
    $val = AvroGMP::decode_long_from_array($ary);
    $this->assertEquals($expected_val, $val);
  }

  /**
   * @return array
   */
  function long_provider()
  {
    return array(array('0', "\x0"),
                 array('1', "\x2"),
                 array('7', "\xe"),
                 array('10000', "\xa0\x9c\x1"),
                 array('2147483647', "\xfe\xff\xff\xff\xf"),
                 array('98765432109', "\xda\x94\x87\xee\xdf\x5"),
                 array('-1', "\x1"),
                 array('-7', "\xd"),
                 array('-10000', "\x9f\x9c\x1"),
                 array('-2147483648', "\xff\xff\xff\xff\xf"),
                 array('-98765432109', "\xd9\x94\x87\xee\xdf\x5")
      );

  }

  /**
   * @return array
   */
  function bit_shift_provider()
  {
                      // val shift lval rval
    return array(
      array('0', 0, '0', '0',
            '0',
            '0'),
      array('0', 1, '0', '0',
            '0',
            '0'),
      array('0', 7, '0', '0',
            '0',
            '0'),
      array('0', 63, '0', '0',
            '0',
            '0'),
      array('1', 0, '1', '1',
            '1',
            '1'),
      array('1', 1, '2', '0',
            '10',
            '0'),
      array('1', 7, '128', '0',
            '10000000',
            '0'),
      array('1', 63, '-9223372036854775808', '0',
            '1000000000000000000000000000000000000000000000000000000000000000',
            '0'),
      array('100', 0, '100', '100',
            '1100100',
            '1100100'),
      array('100', 1, '200', '50',
            '11001000',
            '110010'),
      array('100', 7, '12800', '0',
            '11001000000000',
            '0'),
      array('100', 63, '0', '0',
            '0',
            '0'),
      array('1000000', 0, '1000000', '1000000',
            '11110100001001000000',
            '11110100001001000000'),
      array('1000000', 1, '2000000', '500000',
            '111101000010010000000',
            '1111010000100100000'),
      array('1000000', 7, '128000000', '7812',
            '111101000010010000000000000',
            '1111010000100'),
      array('1000000', 63, '0', '0',
            '0',
            '0'),
      array('2147483647', 0, '2147483647', '2147483647',
            '1111111111111111111111111111111',
            '1111111111111111111111111111111'),
      array('2147483647', 1, '4294967294', '1073741823',
            '11111111111111111111111111111110',
            '111111111111111111111111111111'),
      array('2147483647', 7, '274877906816', '16777215',
            '11111111111111111111111111111110000000',
            '111111111111111111111111'),
      array('2147483647', 63, '-9223372036854775808', '0',
            '1000000000000000000000000000000000000000000000000000000000000000',
            '0'),
      array('10000000000', 0, '10000000000', '10000000000',
            '1001010100000010111110010000000000',
            '1001010100000010111110010000000000'),
      array('10000000000', 1, '20000000000', '5000000000',
            '10010101000000101111100100000000000',
            '100101010000001011111001000000000'),
      array('10000000000', 7, '1280000000000', '78125000',
            '10010101000000101111100100000000000000000',
            '100101010000001011111001000'),
      array('10000000000', 63, '0', '0',
            '0',
            '0'),
      array('9223372036854775807', 0, '9223372036854775807', '9223372036854775807',
            '111111111111111111111111111111111111111111111111111111111111111',
            '111111111111111111111111111111111111111111111111111111111111111'),
      array('9223372036854775807', 1, '-2', '4611686018427387903',
            '1111111111111111111111111111111111111111111111111111111111111110',
            '11111111111111111111111111111111111111111111111111111111111111'),
      array('9223372036854775807', 7, '-128', '72057594037927935',
            '1111111111111111111111111111111111111111111111111111111110000000',
            '11111111111111111111111111111111111111111111111111111111'),
      array('9223372036854775807', 63, '-9223372036854775808', '0',
            '1000000000000000000000000000000000000000000000000000000000000000',
            '0'),
      array('-1', 0, '-1', '-1',
            '1111111111111111111111111111111111111111111111111111111111111111',
            '1111111111111111111111111111111111111111111111111111111111111111'),
      array('-1', 1, '-2', '-1',
            '1111111111111111111111111111111111111111111111111111111111111110',
            '1111111111111111111111111111111111111111111111111111111111111111'),
      array('-1', 7, '-128', '-1',
            '1111111111111111111111111111111111111111111111111111111110000000',
            '1111111111111111111111111111111111111111111111111111111111111111'),
      array('-1', 63, '-9223372036854775808', '-1',
            '1000000000000000000000000000000000000000000000000000000000000000',
            '1111111111111111111111111111111111111111111111111111111111111111'),
      array('-100', 0, '-100', '-100',
            '1111111111111111111111111111111111111111111111111111111110011100',
            '1111111111111111111111111111111111111111111111111111111110011100'),
      array('-100', 1, '-200', '-50',
            '1111111111111111111111111111111111111111111111111111111100111000',
            '1111111111111111111111111111111111111111111111111111111111001110'),
      array('-100', 7, '-12800', '-1',
            '1111111111111111111111111111111111111111111111111100111000000000',
            '1111111111111111111111111111111111111111111111111111111111111111'),
      array('-100', 63, '0', '-1',
            '0',
            '1111111111111111111111111111111111111111111111111111111111111111'),
      array('-1000000', 0, '-1000000', '-1000000',
            '1111111111111111111111111111111111111111111100001011110111000000',
            '1111111111111111111111111111111111111111111100001011110111000000'),
      array('-1000000', 1, '-2000000', '-500000',
            '1111111111111111111111111111111111111111111000010111101110000000',
            '1111111111111111111111111111111111111111111110000101111011100000'),
      array('-1000000', 7, '-128000000', '-7813',
            '1111111111111111111111111111111111111000010111101110000000000000',
            '1111111111111111111111111111111111111111111111111110000101111011'),
      array('-1000000', 63, '0', '-1',
            '0',
            '1111111111111111111111111111111111111111111111111111111111111111'),
      array('-2147483648', 0, '-2147483648', '-2147483648',
            '1111111111111111111111111111111110000000000000000000000000000000',
            '1111111111111111111111111111111110000000000000000000000000000000'),
      array('-2147483648', 1, '-4294967296', '-1073741824',
            '1111111111111111111111111111111100000000000000000000000000000000',
            '1111111111111111111111111111111111000000000000000000000000000000'),
      array('-2147483648', 7, '-274877906944', '-16777216',
            '1111111111111111111111111100000000000000000000000000000000000000',
            '1111111111111111111111111111111111111111000000000000000000000000'),
      array('-2147483648', 63, '0', '-1',
            '0',
            '1111111111111111111111111111111111111111111111111111111111111111'),
      array('-10000000000', 0, '-10000000000', '-10000000000',
            '1111111111111111111111111111110110101011111101000001110000000000',
            '1111111111111111111111111111110110101011111101000001110000000000'),
      array('-10000000000', 1, '-20000000000', '-5000000000',
            '1111111111111111111111111111101101010111111010000011100000000000',
            '1111111111111111111111111111111011010101111110100000111000000000'),
      array('-10000000000', 7, '-1280000000000', '-78125000',
            '1111111111111111111111101101010111111010000011100000000000000000',
            '1111111111111111111111111111111111111011010101111110100000111000'),
      array('-10000000000', 63, '0', '-1',
            '0',
            '1111111111111111111111111111111111111111111111111111111111111111'),
      array('-9223372036854775808', 0, '-9223372036854775808', '-9223372036854775808',
            '1000000000000000000000000000000000000000000000000000000000000000',
            '1000000000000000000000000000000000000000000000000000000000000000'),
      array('-9223372036854775808', 1, '0', '-4611686018427387904',
            '0',
            '1100000000000000000000000000000000000000000000000000000000000000'),
      array('-9223372036854775808', 7, '0', '-72057594037927936',
            '0',
            '1111111100000000000000000000000000000000000000000000000000000000'),
      array('-9223372036854775808', 63, '0', '-1',
            '0',
            '1111111111111111111111111111111111111111111111111111111111111111'),
      );
  }

}
