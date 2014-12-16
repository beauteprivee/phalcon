<?php
namespace BP\Phalcon\Forms {

  /**
   * \BP\Phalcon\Forms\Form
   *
   * This component allows to build forms using an object-oriented interface
   *
   * @package BP\Phalcon
   */
  class Form extends \Phalcon\Di\Injectable
  {
    protected $sName;

    protected $bIsRequired;
    protected $bIsMultiple;

    protected $aData;
    protected $sDataIndex;

    protected $aMessages;

    protected $aElements;

    /**
     * \BP\Phalcon\Forms\Form constructor
     *
     * @param string  $sName
     * @param bool $bIsRequired
     * @param bool $bIsMultiple
     */
    public function __construct($sName, $bIsRequired = false, $bIsMultiple = false)
    {
      $this->sName = $sName;
      $this->bIsMultiple = $bIsMultiple;
      $this->bIsRequired = $bIsRequired;

      if( method_exists($this,'initialize') )$this->initialize();
    }

    /**
     * Used only for embed form
     * Set true if form is required
     *
     * @param bool $bValue
     */
    public function setRequire($bValue)
    {
      $this->bIsRequired = $bValue;
    }

    /**
     * Used only for embed form
     * Set true if your form is a multiple indexed form like [0][yourForm] & [1][yourForm]
     *
     * @param bool $bValue
     */
    public function setMultiple($bValue)
    {
      $this->bIsMultiple = $bValue;
    }


    /**
     * Return the form's name
     *
     * @return string
     */
    public function getName()
    {
      return $this->sName;
    }

    /**
     * Set the form name
     *
     * @param $sName
     */
    public function setName($sName)
    {
      $this->sName = $sName;
    }

    /**
     * Adds an element or form to the form
     *
     * @param \Phalcon\Forms\ElementInterface || \BP\Phalcon\Forms\Form $oElement
     *
     * @return \BP\Phalcon\Forms\Form
     */
    public function add( $oElement )
    {
      $this->aElements[$oElement->getName()] = $oElement;
      $this->aElements[$oElement->getName()]->setName($this->sName.'['.$oElement->getName().']');

      return $this;
    }

    /**
     * Removes an element from the form
     *
     * @param string $sElementName
     *
     * @return bool
     */
    public function remove($sElementName)
    {
      if( isset($this->aElements[$sElementName]) )
      {
        unset($this->aElements[$sElementName]);
        return true;
      }
      return false;
    }

    /**
     * Returns an element added to the form by its name
     *
     * @param $sName
     *
     * @return \Phalcon\Forms\ElementInterface || \BP\Phalcon\Forms\Form
     * @throws \Exception
     */
    public function get($sName)
    {
      if( isset($this->aElements[$sName]) )return $this->aElements[$sName];

      throw new \Exception("Element with ID=" . $sName . " is not part of the form");
    }

    /**
     * Validate One Element
     *
     * @param $oElement \Phalcon\Forms\ElementInterface || \BP\Phalcon\Forms\Form
     * @param $mData
     *
     * @return \Phalcon\Validation\Message\Group
     */
    protected function validateElement($oElement,$mData)
    {
      if( $oElement instanceof \BP\Phalcon\Forms\Form )
      {
        return $oElement->isValid($mData,true);
      }
      else
      {
        $aValidators = $oElement->getValidators();
        for($i=0,$c=count($aValidators),$aPreparedValidators = [];$i<$c;$i++)
        {
          $aPreparedValidators[] = [$oElement->getName(),$aValidators[$i]];
        }
        if( count($aPreparedValidators) != 0)
        {
          $oValidation = new \Phalcon\Validation($aPreparedValidators);
        }

        $aFilters = $oElement->getFilters();
        if(count($aFilters)!=0)
        {
          $oValidation->setFilters($oElement->getName(),$aFilters);
        }

        return $oValidation->validate($mData);
      }
    }

    /**
     * Validates the form
     *
     * @param array $aData
     * @param bool $bIsEmbed
     *
     * @return bool
     */
    public function isValid( $aData = null, $bIsEmbed = false )
    {
      $aData = $bIsEmbed ? isset($aData[$this->sName])?$aData[$this->sName]:array() : $aData;
      $aMessages = array();
      $bIsValid = true;

      if( !is_array($this->aElements) )
      {
        return true;
      }

      if( $this->bIsMultiple == true && $this->bIsRequired == true && count($aData)==0 )
      {
        $aMessages[$this->sName] = new \Phalcon\Validation\Message\Group([new \Phalcon\Validation\Message('Form empty',$this->sName,'Form')]);
      }
      elseif( $this->bIsMultiple == true && count($aData) != 0 )
      {
        foreach($aData as $mKey=>$aValues)
        {
          foreach( $this->aElements as $sName => $oField )
          {
            $this->aElements[$mKey][$sName] = clone $oField;
          }

          foreach( $this->aElements[$mKey] as $sName => $oField )
          {
            $aMessages[$mKey][$sName] = $this->validateElement($oField,$aValues);
          }
        }
      }
      else
      {
        foreach( $this->aElements as $sName => $oField )
        {
          $aMessages[$sName] = $this->validateElement($oField,$aData);
        }
      }


      if(count($aMessages) !=0)
      {
        $this->aMessages = $aMessages;
        $bIsValid = false;
      }


      return $bIsValid;
    }

    /**
     * Returns the messages generated for a specific element
     *
     * @param string $sName
     *
     * @return array
     */
    public function getMessagesFor($sName)
    {
      return isset($this->aMessages[$sName])?$this->aMessages[$sName]:[];
    }

    /**
     * Check if messages were generated for a specific element
     *
     * @param string $sName
     *
     * @return bool
     */
    public function hasMessagesFor($sName)
    {
      return isset($this->aMessages[$sName])&&count($this->aMessages[$sName])!=0?$this->aMessages[$sName]:[];
    }

    /**
     * Returns the messages generated in the validation
     *
     * @return array
     */
    public function getMessages()
    {
      return $this->aMessages;
    }

    /**
     * Return all elements attached to the form
     *
     * @param string $sType field OR form
     *
     * @return array
     */
    public function getElements($sType = null)
    {
      if( count($this->aElements) == 0 )return [];
      if($sType == 'field')
      {
        $aElements = [];
        foreach($this->aElements as $sKey=>$oElement)
        {
          if( !($oElement instanceof \BP\Phalcon\Forms\Form) )
          {
            $aElements[$sKey] = $oElement;
          }
        }
        return $aElements;
      }
      elseif( $sType == 'form' )
      {
        $aElements = [];
        foreach($this->aElements as $sKey=>$oElement)
        {
          if( $oElement instanceof \BP\Phalcon\Forms\Form )
          {
            $aElements[$sKey] = $oElement;
          }
        }
        return $aElements;
      }
      else
      {
        return $this->aElements;
      }
    }

    /**
     * Return Form embed in this form
     *
     * @return array \BP\Phalcon\Forms\Form
     */
    public function getEmbedForm()
    {
      return $this->getElements('form');
    }

    /**
     * Return Element field in this form
     *
     * @return array \Phalcon\Forms\ElementInterface
     */
    public function getFields()
    {
      return $this->getElements('field');
    }


  }

}