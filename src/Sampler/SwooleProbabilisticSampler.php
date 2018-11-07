<?php

namespace ExtraSwoft\Jaeger\Sampler;

use Jaeger\Constants;
use Jaeger\Sampler\Sampler;
use OpenTracing\Ext\Tags;

class SwooleProbabilisticSampler implements Sampler {

    // min 0, max 1
    private $rate = 0;

    private $tags = [];

    
    public function __construct($rate = 0.0001, $ip, $port){
        $this->rate = $rate;
        $this->tags[Constants\SAMPLER_TYPE_TAG_KEY] = 'probabilistic';
        $this->tags[Constants\SAMPLER_PARAM_TAG_KEY] = $rate;
        $this->tags[Tags\PEER_HOST_IPV4] = $ip;
        $this->tags[Tags\PEER_PORT] = $port;
    }


    public function IsSampled(){
        if(mt_rand(1, 1 / $this->rate) == 1){
            return true;
        }else{
            return false;
        }
    }


    public function Close(){
        //nothing to do
    }


    public function getTags(){
        return $this->tags;
    }
}
