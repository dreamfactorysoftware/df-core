<?php

class HelpersTest extends \DreamFactory\Core\Testing\TestCase
{
    public function testArray_get_or()
    {
        $data = [
            'user' => 1,
            'user_id' => 2,
            'name' => 'John'
        ];

        $this->assertEquals(1, array_get_or($data, ['user', 'user_id'], 99));
        $this->assertEquals(2, array_get_or($data, ['user_id', 'user'], 99));
        $this->assertEquals(99, array_get_or($data, ['foo', 'bar'], 99));
        $this->assertEquals(null, array_get_or($data, ['foo', 'bar']));
        $this->assertEquals('John', array_get_or($data, ['foo', 'bar', 'foobar', 'name']));
        $this->assertEquals('Doe', array_get_or($data, ['foo', 'bar', 'foobar', 'last_name', 'first_name'], 'Doe'));
        $this->assertEquals('', array_get_or($data, ['foo', 'bar', 'foobar', 'last_name', 'first_name'], ''));
    }
}