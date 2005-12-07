<?php

class TPostBackOptions extends TComponent
{
	public $_actionUrl='';
	public $_autoPostBack=false;
	public $_clientSubmit=true;
	public $_performValidation=false;
	public $_validationGroup='';
	public $_trackFocus=false;

	public function getActionUrl()
	{
		return $this->_actionUrl;
	}

	public function setActionUrl($value)
	{
		$this->_actionUrl=THttpUtility::quoteJavaScriptString($value);
	}

	public function getAutoPostBack()
	{
		return $this->_autoPostBack;
	}

	public function setAutoPostBack($value)
	{
		$this->_autoPostBack=$value;
	}

	public function getClientSubmit()
	{
		return $this->_clientSubmit;
	}

	public function setClientSubmit($value)
	{
		$this->_clientSubmit=$value;
	}

	public function getPerformValidation()
	{
		return $this->_performValidation;
	}

	public function setPerformValidation($value)
	{
		$this->_performValidation=$value;
	}

	public function getValidationGroup()
	{
		return $this->_validationGroup;
	}

	public function setValidationGroup($value)
	{
		$this->_validationGroup=$value;
	}

	public function getTrackFocus()
	{
		return $this->_trackFocus;
	}

	public function setTrackFocus($value)
	{
		$this->_trackFocus=$value;
	}
}

class TClientScriptManager extends TComponent
{
	const SCRIPT_DIR='Web/Javascripts/js';
	const POSTBACK_FUNC='Prado.doPostBack';
	private $_page;
	private $_hiddenFields=array();
	private $_beginScripts=array();
	private $_endScripts=array();
	private $_scriptIncludes=array();
	private $_onSubmitStatements=array();
	private $_arrayDeclares=array();
	private $_expandoAttributes=array();
	private $_postBackScriptRegistered=false;
	private $_focusScriptRegistered=false;
	private $_scrollScriptRegistered=false;
	private $_publishedScriptFiles=array();

	public function __construct(TPage $owner)
	{
		$this->_page=$owner;
	}

	public function getPostBackEventReference($control,$parameter='',$options=null,$javascriptPrefix=true)
	{
		if(!$options || (!$options->getPerformValidation() && !$options->getTrackFocus() && $options->getClientSubmit() && $options->getActionUrl()==''))
		{
			$this->registerPostBackScript();
			$formID=$this->_page->getForm()->getClientID();
			$postback=self::POSTBACK_FUNC.'(\''.$formID.'\',\''.$control->getUniqueID().'\',\''.THttpUtility::quoteJavaScriptString($parameter).'\')';
			if($options && $options->getAutoPostBack())
				$postback='setTimeout(\''.THttpUtility::quoteJavaScriptString($postback).'\',0)';
			return $javascriptPrefix?'javascript:'.$postback:$postback;
		}
		$opt='';
		$flag=false;
		if($options->getPerformValidation())
		{
			$flag=true;
			$this->registerValidationScript();
			$opt.=',true,';
		}
		else
			$opt.=',false,';
		if($options->getValidationGroup()!=='')
		{
			$flag=true;
			$opt.='"'.$options->getValidationGroup().'",';
		}
		else
			$opt.='\'\',';
		if($options->getActionUrl()!=='')
		{
			$flag=true;
			$this->_page->setCrossPagePostBack(true);
			$opt.='"'.$options->getActionUrl().'",';
		}
		else
			$opt.='null,';
		if($options->getTrackFocus())
		{
			$flag=true;
			$this->registerFocusScript();
			$opt.='true,';
		}
		else
			$opt.='false,';
		if($options->getClientSubmit())
		{
			$flag=true;
			$opt.='true';
		}
		else
			$opt.='false';
		if(!$flag)
			return '';
		$this->registerPostBackScript();
		$formID=$this->_page->getForm()->getUniqueID();
		$postback=self::POSTBACK_FUNC.'(\''.$formID.'\',\''.$control->getUniqueID().'\',\''.THttpUtility::quoteJavaScriptString($parameter).'\''.$opt.')';
		if($options && $options->getAutoPostBack())
			$postback='setTimeout(\''.THttpUtility::quoteJavaScriptString($postback).'\',0)';
		return $javascriptPrefix?'javascript:'.$postback:$postback;
	}

	public function registerPradoScript($scriptFile)
	{
		if(isset($this->_publishedScriptFiles[$scriptFile]))
			$url=$this->_publishedScriptFiles[$scriptFile];
		else
		{
			$url=$this->_page->getService()->getAssetManager()->publishFilePath(Prado::getFrameworkPath().'/'.self::SCRIPT_DIR.'/'.$scriptFile);
			$this->_publishedScriptFiles[$scriptFile]=$url;
			$this->registerScriptInclude('prado:'.$scriptFile,$url);
		}
		return $url;
	}

	protected function registerPostBackScript()
	{
		if(!$this->_postBackScriptRegistered)
		{
			$this->_postBackScriptRegistered=true;
			$this->registerHiddenField(TPage::FIELD_POSTBACK_TARGET,'');
			$this->registerHiddenField(TPage::FIELD_POSTBACK_PARAMETER,'');
			$this->registerPradoScript('base.js');
		}
	}

	public function registerFocusScript($target)
	{
		if(!$this->_focusScriptRegistered)
		{
			$this->_focusScriptRegistered=true;
			$this->registerPradoScript('base.js');
			$this->registerEndScript('prado:focus','Prado.Focus.setFocus("'.THttpUtility::quoteJavaScriptString($target).'");');
		}
	}

	public function registerScrollScript($x,$y)
	{
		if(!$this->_scrollScriptRegistered)
		{
			$this->_scrollScriptRegistered=true;
			$this->registerHiddenField(TPage::FIELD_SCROLL_X,$x);
			$this->registerHiddenField(TPage::FIELD_SCROLL_Y,$y);
			// TBD, need scroll.js
		}
	}

	public function registerDefaultButtonScript($button)
	{
		$this->registerPradoScript('base.js');
		return 'return Prado.Button.fireButton(event,\''.$button->getClientID().'\')';
	}

	public function registerValidationScript()
	{
	}

	public function isHiddenFieldRegistered($key)
	{
		return isset($this->_hiddenFields[$key]);
	}

	public function isScriptBlockRegistered($key)
	{
		return isset($this->_scriptBlocks[$key]);
	}

	public function isScriptIncludeRegistered($key)
	{
		return isset($this->_scriptIncludes[$key]);
	}

	public function isBeginScriptRegistered($key)
	{
		return isset($this->_beginScripts[$key]);
	}

	public function isEndScriptRegistered($key)
	{
		return isset($this->_endScripts[$key]);
	}

	public function isOnSubmitStatementRegistered($key)
	{
		return isset($this->_onSubmitStatements[$key]);
	}

	public function registerArrayDeclaration($name,$value)
	{
		$this->_arrayDeclares[$name][]=$value;
	}

	public function registerScriptInclude($key,$url)
	{
		$this->_scriptIncludes[$key]=$url;
	}

	public function registerHiddenField($name,$value)
	{
		$this->_hiddenFields[$name]=$value;
	}

	public function registerOnSubmitStatement($key,$script)
	{
		$this->_onSubmitStatements[$key]=$script;
	}

	public function registerBeginScript($key,$script)
	{
		$this->_beginScripts[$key]=$script;
	}

	public function registerEndScript($key,$script)
	{
		$this->_endScripts[$key]=$script;
	}

	public function registerExpandoAttribute($controlID,$name,$value)
	{
		$this->_expandoAttributes[$controlID][$name]=$value;
	}

	public function renderArrayDeclarations($writer)
	{
		if(count($this->_arrayDeclares))
		{
			$str="<script type=\"text/javascript\">\n//<![CDATA[\n";
			foreach($this->_arrayDeclares as $name=>$array)
				$str.="var $name=new Array(".implode(',',$array).");\n";
			$str.="\n//]]>\n</script>\n";
			$writer->write($str);
		}
	}

	public function renderScriptIncludes($writer)
	{
		foreach($this->_scriptIncludes as $include)
			$writer->write("<script type=\"text/javascript\" src=\"".THttpUtility::htmlEncode($include)."\"></script>\n");
	}

	public function renderOnSubmitStatements($writer)
	{
		// ???
	}

	public function renderBeginScripts($writer)
	{
		if(count($this->_beginScripts))
			$writer->write("<script type=\"text/javascript\">\n//<![CDATA[\n".implode("\n",$this->_beginScripts)."\n//]]>\n</script>\n");
	}

	public function renderEndScripts($writer)
	{
		if(count($this->_endScripts))
			$writer->write("<script type=\"text/javascript\">\n//<![CDATA[\n".implode("\n",$this->_endScripts)."\n//]]>\n</script>\n");
	}

	public function renderHiddenFields($writer)
	{
		$str='';
		foreach($this->_hiddenFields as $name=>$value)
		{
			$value=THttpUtility::htmlEncode($value);
			$str.="<input type=\"hidden\" name=\"$name\" id=\"$name\" value=\"$value\" />\n";
		}
		if($str!=='')
			$writer->write("<div>\n".$str."</div>\n");
	}

	public function renderExpandoAttributes($writer)
	{
		if(count($this->_expandoAttributes))
		{
			$str="<script type=\"text/javascript\">\n//<![CDATA[\n";
			foreach($this->_expandoAttributes as $controlID=>$attrs)
			{
				$str.="var $controlID = document.all ? document.all[\"$controlID\"] : document.getElementById(\"$controlID\");\n";
				foreach($attrs as $name=>$value)
				{
					if($value===null)
						$str.="{$key}[\"$name\"]=null;\n";
					else
						$str.="{$key}[\"$name\"]=\"$value\";\n";
				}
			}
			$str.="\n//]]>\n</script>\n";
			$writer->write($str);
		}
	}

	public function getHasHiddenFields()
	{
		return count($this->_hiddenFields)>0;
	}

	public function getHasSubmitStatements()
	{
		return count($this->_onSubmitStatements)>0;
	}


	/*
	internal void RenderWebFormsScript(HtmlTextWriter writer)
	private void EnsureEventValidationFieldLoaded();
	internal string GetEventValidationFieldValue();
	public string GetWebResourceUrl(Type type, string resourceName);
	public void RegisterClientScriptResource(Type type, string resourceName);
	internal void RegisterDefaultButtonScript(Control button, $writer, bool useAddAttribute);
	public function SaveEventValidationField();
	public void ValidateEvent(string uniqueId, string argument);
	public function getCallbackEventReference()
	*/
}

?>