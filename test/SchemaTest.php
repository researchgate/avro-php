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
 * Class SchemaExample
 */
class SchemaExample
{
  var $schema_string;
  var $is_valid;
  var $name;
  var $comment;
  var $normalized_schema_string;

  /**
   * SchemaExample constructor.
   * @param $schema_string
   * @param $is_valid
   * @param null $normalized_schema_string
   * @param null $name
   * @param null $comment
   */
  function __construct($schema_string, $is_valid, $normalized_schema_string=null,
                       $name=null, $comment=null)
  {
    $this->schema_string = $schema_string;
    $this->is_valid = $is_valid;
    $this->name = $name ? $name : $schema_string;
    $this->normalized_schema_string = $normalized_schema_string
      ? $normalized_schema_string : json_encode(json_decode($schema_string, true));
    $this->comment = $comment;
  }
}

/**
 * Class SchemaTest
 */
class SchemaTest extends PHPUnit_Framework_TestCase
{
  static $examples = array();
  static $valid_examples = array();

  /**
   * @return array
   */
  protected static function make_primitive_examples()
  {
    $examples = array();
    foreach (array('null', 'boolean',
                   'int', 'long',
                   'float', 'double',
                   'bytes', 'string')
             as $type)
    {
      $examples []= new SchemaExample(sprintf('"%s"', $type), true);
      $examples []= new SchemaExample(sprintf('{"type": "%s"}', $type), true, sprintf('"%s"', $type));
    }
    return $examples;
  }

  protected static function make_examples()
  {
    $primitive_examples = array_merge(array(new SchemaExample('"True"', false),
                                            new SchemaExample('{"no_type": "test"}', false),
                                            new SchemaExample('{"type": "panther"}', false)),
                                        self::make_primitive_examples());

    $array_examples = array(
      new SchemaExample('{"type": "array", "items": "long"}', true),
      new SchemaExample('
    {"type": "array",
     "items": {"type": "enum", "name": "Test", "symbols": ["A", "B"]}}
    ', true));

    $map_examples = array(
      new SchemaExample('{"type": "map", "values": "long"}', true),
      new SchemaExample('
    {"type": "map",
     "values": {"type": "enum", "name": "Test", "symbols": ["A", "B"]}}
    ', true));

    $union_examples = array(
      new SchemaExample('["string", "null", "long"]', true),
      new SchemaExample('["null", "null"]', false),
      new SchemaExample('["long", "long"]', false),
      new SchemaExample('
    [{"type": "array", "items": "long"}
     {"type": "array", "items": "string"}]
    ', false),
      new SchemaExample('["long",
                          {"type": "long"},
                          "int"]', false),
      new SchemaExample('["long",
                          {"type": "array", "items": "long"},
                          {"type": "map", "values": "long"},
                          "int"]', true),
      new SchemaExample('["long",
                          ["string", "null"],
                          "int"]', false),
      new SchemaExample('["long",
                          ["string", "null"],
                          "int"]', false),
      new SchemaExample('["null", "boolean", "int", "long", "float", "double",
                          "string", "bytes",
                          {"type": "array", "items":"int"},
                          {"type": "map", "values":"int"},
                          {"name": "bar", "type":"record",
                           "fields":[{"name":"label", "type":"string"}]},
                          {"name": "foo", "type":"fixed",
                           "size":16},
                          {"name": "baz", "type":"enum", "symbols":["A", "B", "C"]}
                         ]', true, '["null","boolean","int","long","float","double","string","bytes",{"type":"array","items":"int"},{"type":"map","values":"int"},{"type":"record","name":"bar","fields":[{"name":"label","type":"string"}]},{"type":"fixed","name":"foo","size":16},{"type":"enum","name":"baz","symbols":["A","B","C"]}]'),
      new SchemaExample('
    [{"name":"subtract", "namespace":"com.example",
      "type":"record",
      "fields":[{"name":"minuend", "type":"int"},
                {"name":"subtrahend", "type":"int"}]},
      {"name": "divide", "namespace":"com.example",
      "type":"record",
      "fields":[{"name":"quotient", "type":"int"},
                {"name":"dividend", "type":"int"}]},
      {"type": "array", "items": "string"}]
    ', true, '[{"type":"record","name":"subtract","namespace":"com.example","fields":[{"name":"minuend","type":"int"},{"name":"subtrahend","type":"int"}]},{"type":"record","name":"divide","namespace":"com.example","fields":[{"name":"quotient","type":"int"},{"name":"dividend","type":"int"}]},{"type":"array","items":"string"}]'),
      );

    $fixed_examples = array(
      new SchemaExample('{"type": "fixed", "name": "Test", "size": 1}', true),
      new SchemaExample('
    {"type": "fixed",
     "name": "MyFixed",
     "namespace": "org.apache.hadoop.avro",
     "size": 1}
    ', true),
      new SchemaExample('
    {"type": "fixed",
     "name": "Missing size"}
    ', false),
      new SchemaExample('
    {"type": "fixed",
     "size": 314}
    ', false),
      new SchemaExample('{"type":"fixed","name":"ex","doc":"this should be ignored","size": 314}',
                        true,
                        '{"type":"fixed","name":"ex","size":314}'),
      new SchemaExample('{"name": "bar",
                          "namespace": "com.example",
                          "type": "fixed",
                          "size": 32 }', true,
                        '{"type":"fixed","name":"bar","namespace":"com.example","size":32}'),
      new SchemaExample('{"name": "com.example.bar",
                          "type": "fixed",
                          "size": 32 }', true,
        '{"type":"fixed","name":"bar","namespace":"com.example","size":32}'));

    $fixed_examples []= new SchemaExample(
      '{"type":"fixed","name":"_x.bar","size":4}', true,
      '{"type":"fixed","name":"bar","namespace":"_x","size":4}');
    $fixed_examples []= new SchemaExample(
      '{"type":"fixed","name":"baz._x","size":4}', true,
      '{"type":"fixed","name":"_x","namespace":"baz","size":4}');
    $fixed_examples []= new SchemaExample(
      '{"type":"fixed","name":"baz.3x","size":4}', false);

    $enum_examples = array(
      new SchemaExample('{"type": "enum", "name": "Test", "symbols": ["A", "B"]}', true),
      new SchemaExample('
    {"type": "enum",
     "name": "Status",
     "symbols": "Normal Caution Critical"}
    ', false),
      new SchemaExample('
    {"type": "enum",
     "name": [ 0, 1, 1, 2, 3, 5, 8 ],
     "symbols": ["Golden", "Mean"]}
    ', false),
      new SchemaExample('
    {"type": "enum",
     "symbols" : ["I", "will", "fail", "no", "name"]}
    ', false),
      new SchemaExample('
    {"type": "enum",
     "name": "Test"
     "symbols" : ["AA", "AA"]}
    ', false),
      new SchemaExample('{"type":"enum","name":"Test","symbols":["AA", 16]}',
                        false),
      new SchemaExample('
    {"type": "enum",
     "name": "blood_types",
     "doc": "AB is freaky.",
     "symbols" : ["A", "AB", "B", "O"]}
    ', true),
      new SchemaExample('
    {"type": "enum",
     "name": "blood-types",
     "doc": 16,
     "symbols" : ["A", "AB", "B", "O"]}
    ', false)
      );


    $record_examples = array();
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Test",
     "fields": [{"name": "f",
                 "type": "long"}]}
    ', true);
    $record_examples []= new SchemaExample('
    {"type": "error",
     "name": "Test",
     "fields": [{"name": "f",
                 "type": "long"}]}
    ', true);
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Node",
     "fields": [{"name": "label", "type": "string"},
                {"name": "children",
                 "type": {"type": "array", "items": "Node"}}]}
    ', true);
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "ListLink",
     "fields": [{"name": "car", "type": "int"},
                {"name": "cdr", "type": "ListLink"}]}
    ', true);
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Lisp",
     "fields": [{"name": "value",
                 "type": ["null", "string"]}]}
    ', true);
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Lisp",
     "fields": [{"name": "value",
                 "type": ["null", "string",
                          {"type": "record",
                           "name": "Cons",
                           "fields": [{"name": "car", "type": "string"},
                                      {"name": "cdr", "type": "string"}]}]}]}
    ', true);
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Lisp",
     "fields": [{"name": "value",
                 "type": ["null", "string",
                          {"type": "record",
                           "name": "Cons",
                           "fields": [{"name": "car", "type": "Lisp"},
                                      {"name": "cdr", "type": "Lisp"}]}]}]}
    ', true);
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "HandshakeRequest",
     "namespace": "org.apache.avro.ipc",
     "fields": [{"name": "clientHash",
                 "type": {"type": "fixed", "name": "MD5", "size": 16}},
                {"name": "meta",
                 "type": ["null", {"type": "map", "values": "bytes"}]}]}
    ', true);
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "HandshakeRequest",
     "namespace": "org.apache.avro.ipc",
     "fields": [{"name": "clientHash",
                 "type": {"type": "fixed", "name": "MD5", "size": 16}},
                {"name": "clientProtocol", "type": ["null", "string"]},
                {"name": "serverHash", "type": "MD5"},
                {"name": "meta",
                 "type": ["null", {"type": "map", "values": "bytes"}]}]}
    ', true);
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "HandshakeResponse",
     "namespace": "org.apache.avro.ipc",
     "fields": [{"name": "match",
                 "type": {"type": "enum",
                          "name": "HandshakeMatch",
                          "symbols": ["BOTH", "CLIENT", "NONE"]}},
                {"name": "serverProtocol", "type": ["null", "string"]},
                {"name": "serverHash",
                 "type": ["null",
                          {"name": "MD5", "size": 16, "type": "fixed"}]},
                {"name": "meta",
                 "type": ["null", {"type": "map", "values": "bytes"}]}]}
    ', true,
     '{"type":"record","name":"HandshakeResponse","namespace":"org.apache.avro.ipc","fields":[{"name":"match","type":{"type":"enum","name":"HandshakeMatch","symbols":["BOTH","CLIENT","NONE"]}},{"name":"serverProtocol","type":["null","string"]},{"name":"serverHash","type":["null",{"type":"fixed","name":"MD5","size":16}]},{"name":"meta","type":["null",{"type":"map","values":"bytes"}]}]}'
      );
    $record_examples []= new SchemaExample('{"type": "record",
 "namespace": "org.apache.avro",
 "name": "Interop",
 "fields": [{"type": {"fields": [{"type": {"items": "org.apache.avro.Node",
                                           "type": "array"},
                                  "name": "children"}],
                      "type": "record",
                      "name": "Node"},
             "name": "recordField"}]}
', true, '{"type":"record","name":"Interop","namespace":"org.apache.avro","fields":[{"name":"recordField","type":{"type":"record","name":"Node","fields":[{"name":"children","type":{"type":"array","items":"Node"}}]}}]}');
    $record_examples [] = new SchemaExample('{"type": "record",
 "namespace": "org.apache.avro",
 "name": "Interop",
 "fields": [{"type": {"symbols": ["A", "B", "C"], "type": "enum", "name": "Kind"},
             "name": "enumField"},
            {"type": {"fields": [{"type": "string", "name": "label"},
                                 {"type": {"items": "org.apache.avro.Node", "type": "array"},
                                  "name": "children"}],
                      "type": "record",
                      "name": "Node"},
             "name": "recordField"}]}', true, '{"type":"record","name":"Interop","namespace":"org.apache.avro","fields":[{"name":"enumField","type":{"type":"enum","name":"Kind","symbols":["A","B","C"]}},{"name":"recordField","type":{"type":"record","name":"Node","fields":[{"name":"label","type":"string"},{"name":"children","type":{"type":"array","items":"Node"}}]}}]}');

    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Interop",
     "namespace": "org.apache.avro",
     "fields": [{"name": "intField", "type": "int"},
                {"name": "longField", "type": "long"},
                {"name": "stringField", "type": "string"},
                {"name": "boolField", "type": "boolean"},
                {"name": "floatField", "type": "float"},
                {"name": "doubleField", "type": "double"},
                {"name": "bytesField", "type": "bytes"},
                {"name": "nullField", "type": "null"},
                {"name": "arrayField",
                 "type": {"type": "array", "items": "double"}},
                {"name": "mapField",
                 "type": {"type": "map",
                          "values": {"name": "Foo",
                                     "type": "record",
                                     "fields": [{"name": "label",
                                                 "type": "string"}]}}},
                {"name": "unionField",
                 "type": ["boolean",
                          "double",
                          {"type": "array", "items": "bytes"}]},
                {"name": "enumField",
                 "type": {"type": "enum",
                          "name": "Kind",
                          "symbols": ["A", "B", "C"]}},
                {"name": "fixedField",
                 "type": {"type": "fixed", "name": "MD5", "size": 16}},
                {"name": "recordField",
                 "type": {"type": "record",
                          "name": "Node",
                          "fields": [{"name": "label", "type": "string"},
                                     {"name": "children",
                                      "type": {"type": "array",
                                               "items": "Node"}}]}}]}
    ', true,
    '{"type":"record","name":"Interop","namespace":"org.apache.avro","fields":[{"name":"intField","type":"int"},{"name":"longField","type":"long"},{"name":"stringField","type":"string"},{"name":"boolField","type":"boolean"},{"name":"floatField","type":"float"},{"name":"doubleField","type":"double"},{"name":"bytesField","type":"bytes"},{"name":"nullField","type":"null"},{"name":"arrayField","type":{"type":"array","items":"double"}},{"name":"mapField","type":{"type":"map","values":{"type":"record","name":"Foo","fields":[{"name":"label","type":"string"}]}}},{"name":"unionField","type":["boolean","double",{"type":"array","items":"bytes"}]},{"name":"enumField","type":{"type":"enum","name":"Kind","symbols":["A","B","C"]}},{"name":"fixedField","type":{"type":"fixed","name":"MD5","size":16}},{"name":"recordField","type":{"type":"record","name":"Node","fields":[{"name":"label","type":"string"},{"name":"children","type":{"type":"array","items":"Node"}}]}}]}');
    $record_examples []= new SchemaExample('{"type": "record", "namespace": "org.apache.avro", "name": "Interop", "fields": [{"type": "int", "name": "intField"}, {"type": "long", "name": "longField"}, {"type": "string", "name": "stringField"}, {"type": "boolean", "name": "boolField"}, {"type": "float", "name": "floatField"}, {"type": "double", "name": "doubleField"}, {"type": "bytes", "name": "bytesField"}, {"type": "null", "name": "nullField"}, {"type": {"items": "double", "type": "array"}, "name": "arrayField"}, {"type": {"type": "map", "values": {"fields": [{"type": "string", "name": "label"}], "type": "record", "name": "Foo"}}, "name": "mapField"}, {"type": ["boolean", "double", {"items": "bytes", "type": "array"}], "name": "unionField"}, {"type": {"symbols": ["A", "B", "C"], "type": "enum", "name": "Kind"}, "name": "enumField"}, {"type": {"type": "fixed", "name": "MD5", "size": 16}, "name": "fixedField"}, {"type": {"fields": [{"type": "string", "name": "label"}, {"type": {"items": "org.apache.avro.Node", "type": "array"}, "name": "children"}], "type": "record", "name": "Node"}, "name": "recordField"}]}
', true, '{"type":"record","name":"Interop","namespace":"org.apache.avro","fields":[{"name":"intField","type":"int"},{"name":"longField","type":"long"},{"name":"stringField","type":"string"},{"name":"boolField","type":"boolean"},{"name":"floatField","type":"float"},{"name":"doubleField","type":"double"},{"name":"bytesField","type":"bytes"},{"name":"nullField","type":"null"},{"name":"arrayField","type":{"type":"array","items":"double"}},{"name":"mapField","type":{"type":"map","values":{"type":"record","name":"Foo","fields":[{"name":"label","type":"string"}]}}},{"name":"unionField","type":["boolean","double",{"type":"array","items":"bytes"}]},{"name":"enumField","type":{"type":"enum","name":"Kind","symbols":["A","B","C"]}},{"name":"fixedField","type":{"type":"fixed","name":"MD5","size":16}},{"name":"recordField","type":{"type":"record","name":"Node","fields":[{"name":"label","type":"string"},{"name":"children","type":{"type":"array","items":"Node"}}]}}]}');
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "ipAddr",
     "fields": [{"name": "addr",
                 "type": [{"name": "IPv6", "type": "fixed", "size": 16},
                          {"name": "IPv4", "type": "fixed", "size": 4}]}]}
    ', true,
    '{"type":"record","name":"ipAddr","fields":[{"name":"addr","type":[{"type":"fixed","name":"IPv6","size":16},{"type":"fixed","name":"IPv4","size":4}]}]}');
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Address",
     "fields": [{"type": "string"},
                {"type": "string", "name": "City"}]}
    ', false);
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Event",
     "fields": [{"name": "Sponsor"},
                {"name": "City", "type": "string"}]}
    ', false);
    $record_examples []= new SchemaExample('
    {"type": "record",
     "fields": "His vision, from the constantly passing bars,"
     "name", "Rainer"}
    ', false);
     $record_examples []= new SchemaExample('
    {"name": ["Tom", "Jerry"],
     "type": "record",
     "fields": [{"name": "name", "type": "string"}]}
    ', false);
     $record_examples []= new SchemaExample('
    {"type":"record","name":"foo","doc":"doc string",
     "fields":[{"name":"bar", "type":"int", "order":"ascending", "default":1}]}
',
                                            true,
                                            '{"type":"record","name":"foo","doc":"doc string","fields":[{"name":"bar","type":"int","default":1,"order":"ascending"}]}');
     $record_examples []= new SchemaExample('
    {"type":"record", "name":"foo", "doc":"doc string",
     "fields":[{"name":"bar", "type":"int", "order":"bad"}]}
', false);
     // `"default":null` should not be lost in `to_avro`.
     $record_examples []= new SchemaExample(
        '{"type":"record","name":"foo","fields":[{"name":"bar","type":["null","string"],"default":null}]}',
        true,
        '{"type":"record","name":"foo","fields":[{"name":"bar","type":["null","string"],"default":null}]}');
    // Don't lose the "doc" attributes of record fields.
    $record_examples []= new SchemaExample(
      '{"type":"record","name":"foo","fields":[{"name":"bar","type":["null","string"],"doc":"Bar name."}]}',
      true,
      '{"type":"record","name":"foo","fields":[{"name":"bar","type":["null","string"],"doc":"Bar name."}]}');

    $primitive_examples []= new SchemaExample(
        '{ "type": "bytes", "logicalType": "decimal", "precision": 4, "scale": 2 }',
        true
    );
    $fixed_examples []= new SchemaExample(
        '{ "type": "fixed", "size": 32, "name": "hash", "logicalType": "md5" }',
        true,
        '{"type":"fixed","name":"hash","logicalType":"md5","size":32}'
    );
    $enum_examples []= new SchemaExample(
    '{"type": "enum", "logicalType": "foo", "name": "foo", "symbols": ["FOO", "BAR"], "foo": "bar"}',
        true,
        '{"type":"enum","name":"foo","logicalType":"foo","foo":"bar","symbols":["FOO","BAR"]}'
    );
    $array_examples []= new SchemaExample(
    '{"type": "array", "logicalType": "foo", "items": "string", "foo": "bar"}',
        true,
        '{"type":"array","items":"string","logicalType":"foo","foo":"bar"}'
    );
    $map_examples []= new SchemaExample(
        '{"type": "map", "logicalType": "foo", "values": "long", "foo": "bar"}',
        true,
        '{"type":"map","values":"long","logicalType":"foo","foo":"bar"}'
    );
    $record_examples []= new SchemaExample(
        '{ "type": "record", "name": "foo", "logicalType": "bar", "fields": [], "foo": "bar" }',
        true,
        '{"type":"record","name":"foo","logicalType":"bar","foo":"bar","fields":[]}'
    );

    self::$examples = array_merge($primitive_examples,
                                  $fixed_examples,
                                  $enum_examples,
                                  $array_examples,
                                  $map_examples,
                                  $union_examples,
                                  $record_examples);
    self::$valid_examples = array();
    foreach (self::$examples as $example)
    {
      if ($example->is_valid)
        self::$valid_examples []= $example;
    }
  }

  function test_json_decode()
  {
    $this->assertEquals(json_decode('null', true), null);
    $this->assertEquals(json_decode('32', true), 32);
    $this->assertEquals(json_decode('"32"', true), '32');
    $this->assertEquals((array) json_decode('{"foo": 27}'), array("foo" => 27));
    $this->assertTrue(is_array(json_decode('{"foo": 27}', true)));
    $this->assertEquals(json_decode('{"foo": 27}', true), array("foo" => 27));
    $this->assertEquals(json_decode('["bar", "baz", "blurfl"]', true),
                        array("bar", "baz", "blurfl"));
    $this->assertFalse(is_array(json_decode('null', true)));
    $this->assertEquals(json_decode('{"type": "null"}', true), array("type" => 'null'));
    $this->assertEquals(json_decode('"boolean"'), 'boolean');
  }

  function parse_bad_json_provider()
  {
    return array(
      // Valid
      array('{"type": "array", "items": "long"}', null),
      // Trailing comma
      array('{"type": "array", "items": "long", }', "JSON decode error 4: Syntax error"),
      // Wrong quotes
      array("{'type': 'array', 'items': 'long'}", "JSON decode error 4: Syntax error"),
      // Binary data
      array("\x11\x07", "JSON decode error 3: Control character error, possibly incorrectly encoded"),
    );
  }

  /**
   * @dataProvider parse_bad_json_provider
   */
  function test_parse_bad_json($json, $failure)
  {
    if (defined('HHVM_VERSION'))
    {
      // Under HHVM, json_decode is not as strict and feature complete as standard PHP.
      $this->markTestSkipped();
    }
    try
    {
      $schema = AvroSchema::parse($json);
      $this->assertEquals($failure, null);
    }
    catch (AvroSchemaParseException $e)
    {
      $this->assertEquals($failure, $e->getMessage());
    }
  }

  /**
   * @return array
   */
  function schema_examples_provider()
  {
    self::make_examples();
    $ary = array();
    foreach (self::$examples as $example)
      $ary []= array($example);
    return $ary;
  }

  /**
   * @dataProvider schema_examples_provider
   * @param $example
   */
  function test_parse($example)
  {
    $schema_string = $example->schema_string;
    try
    {
      $normalized_schema_string = $example->normalized_schema_string;
      $schema = AvroSchema::parse($schema_string);
      $this->assertTrue($example->is_valid,
                        sprintf("schema_string: %s\n",
                                $schema_string));
      // strval() roughly does to_avro() + json_encode()
      $this->assertEquals($normalized_schema_string, strval($schema));
    }
    catch (AvroSchemaParseException $e)
    {
      $this->assertFalse($example->is_valid,
                         sprintf("schema_string: %s\n%s",
                                 $schema_string,
                                 $e->getMessage()));
    }
  }

  function test_record_doc()
  {
    $json = '{"type": "record", "name": "foo", "doc": "Foo doc.",
              "fields": [{"name": "bar", "type": "int", "doc": "Bar doc."}]}';
    $schema = AvroSchema::parse($json);
    $this->assertEquals($schema->doc(), "Foo doc.");
    $fields = $schema->fields();
    $this->assertCount(1, $fields);
    $bar = $fields[0];
    $this->assertEquals($bar->doc(), "Bar doc.");
  }

  function test_enum_doc()
  {
    $json = '{"type": "enum", "name": "blood_types", "doc": "AB is freaky.", "symbols": ["A", "AB", "B", "O"]}';
    $schema = AvroSchema::parse($json);
    $this->assertEquals($schema->doc(), "AB is freaky.");
  }

  function test_logical_type()
  {
    $json = '{ "type": "bytes", "logicalType": "decimal", "precision": 4, "scale": 2 }';
    $schema = AvroSchema::parse($json);
    $this->assertEquals($schema->logical_type(), "decimal");
    $this->assertEquals($schema->extra_attributes(), ["precision" => 4, "scale" => 2]);
  }
}
