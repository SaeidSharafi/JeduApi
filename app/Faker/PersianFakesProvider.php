<?php

namespace App\Faker;

use Faker\Provider\Base;

class PersianFakesProvider extends Base
{
    private array $objects = [
        'mobile' => [
            912 , 931 , 932 , 933 , 901 , 921 , 919 , 912 , 913 , 917 ,
            915 , 916 , 910 , 939 , 938 , 937 , 918 , 914 , 911 , 934
        ],
    ];
    public function mobile(): string
    {
        $prefix = $this->getRandomKey('mobile');
        $phone = ('0' . $prefix . randomNumber(7));
        return (strlen($phone) !== 11 ? $phone . rand(1, 9) : $phone);
    }


    /**
     * return random data in object
     * $object is a name of index of librrary
     */
    private function getRandomKey($object = null): string
    {
        $name = 0;
        $array = [];

        if (is_array($object)) {
            $array = $object;
            $name = array_rand($object);
        } elseif (is_string($object)) {
            $array = $this->objects[$object];
            $name = array_rand($array);
        }

        return (string)$array[$name];
    }
}
