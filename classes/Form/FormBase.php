<?php

namespace AIJOH\Form;

interface FormBase {
    /**
     * getHeaderTag
     * @return mixed
     */
    public function getHeaderTag();
    
    /**
     *
     * @return string
     */
    public function getFormTag() : string;
    
    /**
     * getFooterTag
     * @return string
     */
    public function getFooterTag() : string;
    
    /**
      * receive
     * @return mixed
     */
    public function receive();
}