<?
    // Klassendefinition
    class IPS2FlightRadar24Viewer extends IPSModule 
    {
	public function Destroy() 
	{
		//Never delete this line!
		parent::Destroy();
		$this->SetTimerInterval("Timer_1", 0);
	}  
	    
	// Überschreibt die interne IPS_Create($id) Funktion
        public function Create() 
        {
            	// Diese Zeile nicht löschen.
            	parent::Create();
		$this->ConnectParent("{EE90A447-53E8-9B5F-B7FA-6F5E3A87F74C}");
		$this->RegisterPropertyBoolean("Open", false);
		$this->RegisterPropertyString("DeviceNumber", "");
		$this->RegisterPropertyInteger("Timer_1", 3);
		$this->RegisterTimer("Timer_1", 0, 'IPS2VoIPMobileFinder_Disconnect($_IPS["TARGET"]);');
		
		//Status-Variablen anlegen
		$this->RegisterProfileInteger("IPS2VoIP.StartStop", "Telephone", "", "", 0, 1, 0);
		IPS_SetVariableProfileAssociation("IPS2VoIP.StartStop", 0, "Start", "Telephone", 0x00FF00);
		IPS_SetVariableProfileAssociation("IPS2VoIP.StartStop", 1, "Stop", "Telephone", 0xFF0000);
		
		//Status-Variablen anlegen
		$this->RegisterVariableInteger("State", "Ruf", "IPS2VoIP.StartStop", 10);
		$this->EnableAction("State");
        }
 	
	public function GetConfigurationForm() 
	{ 
		$arrayStatus = array(); 
		$arrayStatus[] = array("code" => 101, "icon" => "inactive", "caption" => "Instanz wird erstellt"); 
		$arrayStatus[] = array("code" => 102, "icon" => "active", "caption" => "Instanz ist aktiv");
		$arrayStatus[] = array("code" => 104, "icon" => "inactive", "caption" => "Instanz ist inaktiv");
		$arrayStatus[] = array("code" => 202, "icon" => "error", "caption" => "Fehlerhafte Schnittstelle!");
				
		$arrayElements = array(); 
		
		$arrayElements[] = array("name" => "Open", "type" => "CheckBox",  "caption" => "Aktiv");
		$arrayElements[] = array("type" => "Label", "label" => "Telefonnummer des Endgerätes"); 
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "DeviceNumber", "caption" => "Telefonnummer");
		$arrayElements[] = array("type" => "Label", "label" => "Laufzeit des Klingelsignals (3 bis 15 Sekunden)"); 
		$arrayElements[] = array("type" => "IntervalBox", "name" => "Timer_1", "caption" => "s");
		$arrayElements[] = array("type" => "Label", "label" => "_____________________________________________________________________________________________________");
		$arrayElements[] = array("type" => "Label", "label" => "Test Center"); 
		$arrayElements[] = array("type" => "TestCenter", "name" => "TestCenter");
		
 		return JSON_encode(array("status" => $arrayStatus, "elements" => $arrayElements)); 		 
 	}       
	   
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() 
        {
            	// Diese Zeile nicht löschen
            	parent::ApplyChanges();
		
		SetValueInteger($this->GetIDForIdent("State"), 1);
		
		If ($this->ReadPropertyBoolean("Open") == true) {
			// Prüfen des ausgeählten Parents
			$DeviceNumber = $this->ReadPropertyString("DeviceNumber");
			$CheckDeviceNumber = $this->CheckDeviceNumber($DeviceNumber);
			
			If ($CheckDeviceNumber == true) {
				$this->SetStatus(102);
			}
			else {
				$this->SetStatus(202);
			}
			$this->SetTimerInterval("Timer_1", 0);
		}
		else {
			$this->SetStatus(104);
			$this->SetTimerInterval("Timer_1", 0);
		}	
	}
	
	public function RequestAction($Ident, $Value) 
	{
  		switch($Ident) {
	        case "State":
			$State = GetValueInteger($this->GetIDForIdent("State"));
	            	If (($Value == 0) AND ($State == 1)) {
				$this->Connect();
			}
			elseif ($Value == 1) {
				$this->Disconnect();
			}
		break;
	        default:
	            throw new Exception("Invalid Ident");
	    }
	}
	    
	// Beginn der Funktionen
	private function Connect()
	{
  		$CurrentStatus = $this->GetStatus();
		If (($this->ReadPropertyBoolean("Open") == true) AND ($CurrentStatus == 102)) {
			
			
			SetValueInteger($this->GetIDForIdent("State"), 0);
			$DeviceNumber = $this->ReadPropertyString("DeviceNumber");
			//$VoIP_InstanceID = $this->ReadPropertyInteger("VoIP_InstanceID");
			$Timer_1 = $this->ReadPropertyInteger("Timer_1");
			$Timer_1 = min(15, max(3, $Timer_1));
			
			$ConnectionID = $this->SendDataToParent(json_encode(Array("DataID"=> "{7E7666EA-A882-7DBB-418A-3A64E00CAB4C}", 
						"Function" => "Connect", "DeviceNumber" => $this->ReadPropertyString("DeviceNumber") )));

			$this->SetBuffer("ConnectionID", $ConnectionID);
			$this->SetTimerInterval("Timer_1", $Timer_1 * 1000);
		}
	}
	
	public function Disconnect()
	{
  		$CurrentStatus = $this->GetStatus();
		If (($this->ReadPropertyBoolean("Open") == true) AND ($CurrentStatus == 102)) {
			SetValueInteger($this->GetIDForIdent("State"), 1);
			$ConnectionID = intval($this->GetBuffer("ConnectionID"));
			
			$ConnectionID = $this->SendDataToParent(json_encode(Array("DataID"=> "{7E7666EA-A882-7DBB-418A-3A64E00CAB4C}", 
						"Function" => "Disconnect", "ConnectionID" => $ConnectionID )));

			$this->SetTimerInterval("Timer_1", 0);
			$this->SetBuffer("ConnectionID", 0);
		}
	}
	    
	private function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
	{
	        if (!IPS_VariableProfileExists($Name))
	        {
	            IPS_CreateVariableProfile($Name, 1);
	        }
	        else
	        {
	            $profile = IPS_GetVariableProfile($Name);
	            if ($profile['ProfileType'] != 1)
	                throw new Exception("Variable profile type does not match for profile " . $Name);
	        }
	        IPS_SetVariableProfileIcon($Name, $Icon);
	        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
	        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);        
	} 
	    
	private function CheckDeviceNumber(string $DeviceNumber)
	{
		$Result = false;
		If (strlen($DeviceNumber) > 0) {
			if (preg_match("#^[0-9*]+$#", $DeviceNumber)) {
				$Result = true;
			}
			else {
				Echo "Fehlerhafte Telefonnummer! \n(zulässige Zeichen: 0-9 *)";
			}
		}
		else {
			Echo "Fehlende Telefonnummer! \n(zulässige Zeichen: 0-9 *)";
		}
	return $Result;
	}
}
?>
