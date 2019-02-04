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

require_once('test_helper.php');

/**
 * Tests against a preexisting file created by a different library
 */
class FileIOTest extends PHPUnit_Framework_TestCase
{
    protected function tearDown()
    {
        $file = $this->getTmpFile();
        if (file_exists($file))
            unlink($file);
    }

    private function getFileName()
    {
        return __DIR__ . '/data/users.avro';
    }

    private function getTmpFile()
    {
        return __DIR__ . '/tmp/users.avro';
    }

    private function read()
    {
        $fileName = $this->getFileName();
        $reader = AvroDataIO::open_file($fileName);
        return $reader->data();
    }

    public function testReading()
    {
        $expected = [
            [
                'name' => 'Alyssa',
                'favorite_color' => null,
                'favorite_numbers' => [3, 9, 15, 20],
            ],
            [
                'name' => 'Ben',
                'favorite_color' => 'red',
                'favorite_numbers' => [],
            ]
        ];
        $this->assertEquals($expected, $this->read());
    }

    /**
     * Doesn't work because due to Avro format peculiarities mean that no two
     * encodings of the same data will be binary equal.
     */
    public function disabled_testRoundTrip()
    {
        $inFile = $this->getFileName();
        $outFile = $this->getTmpFile();
        $schemaFile = __DIR__ . '/data/user.avsc';
        $data = $this->read();
        $schema = file_get_contents($schemaFile);
        $writer = AvroDataIO::open_file($outFile, 'w', $schema);
        foreach ($data as $record)
        {
            $writer->append($record);
        }
        $writer->close();

        $oldData = file_get_contents($inFile);
        $newData = file_get_contents($outFile);
        if ($oldData !== $newData)
        {
            $diff = shell_exec("bash -c \"diff -y -W 150 <(xxd '$inFile') <(xxd '$outFile')\"");
            $this->fail("Round trip failed, files not equal:\n$diff");
        }
        $this->assertTrue(true, 'Dummy assert to prevent this test from being marked as risky');
    }
}
