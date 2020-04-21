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
		$this->RegisterPropertyBoolean("Open", false);
		$this->RegisterPropertyString("IP", "127.0.0.1");
		$this->RegisterPropertyInteger("Timer_1", 3);
		$this->RegisterTimer("Timer_1", 0, 'IPS2FlightRadar24Viewer_DataUpdate($_IPS["TARGET"]);');
		
		//Status-Variablen anlegen
		$this->RegisterVariableString("Statistics", "Statistik", "~HTMLBox", 10);
		$this->RegisterVariableString("Aircrafts", "Flugzeuge", "~HTMLBox", 20);
		
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
		$arrayElements[] = array("type" => "Label", "label" => "IP des Flightradar24-Gerätes"); 
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "IP", "caption" => "IP");
		
		
 		return JSON_encode(array("status" => $arrayStatus, "elements" => $arrayElements)); 		 
 	}       
	   
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() 
        {
            	// Diese Zeile nicht löschen
            	parent::ApplyChanges();
		
		If ($this->ReadPropertyBoolean("Open") == true) {
			$IP = $this->ReadPropertyString("IP");
			If (filter_var($IP, FILTER_VALIDATE_IP)) {
				$this->DataUpdate();
				$this->SetStatus(102);
				$this->SetTimerInterval("Timer_1", 60 * 1000);
			}
			else {
				Echo "Syntax der IP inkorrekt!";
				$this->SendDebug("ApplyChanges", "Syntax der IP inkorrekt!", 0);
				$this->SetStatus(202);
				$this->SetTimerInterval("Timer_1", 0);
			}
		}
		else {
			$this->SetStatus(104);
			$this->SetTimerInterval("Timer_1", 0);
		}	
	}
	    
	// Beginn der Funktionen
	
	public function DataUpdate()
	{
		If ($this->ReadPropertyBoolean("Open") == true) {
			$this->Statistics();
		}
	}
	    
	private function Statistics()
	{
    		$IP = $this->ReadPropertyString("IP");
    		$StatisticsJSON = file_get_contents('http://'.$IP.'/dump1090/data/stats.json');
    		If ($StatisticsJSON === false) {
         		$this->SendDebug("Statistics", "Fehler beim Lesen der Datei!", 0);
    		}
    		else {
        		$Statistics = array();
        		$Statistics = json_decode($StatisticsJSON);
        		If (is_object($Statistics) == false) {
            			$this->SendDebug("Statistics", "Datei ist kein Array!", 0);
        		}
        		else {
            			$StatisticArray = array("latest" => "Aktuell", "last1min" => "Letzte Minute", "last5min" => "Letzte 5 Minuten", "last15min" => "Letzte 15 Minuten", "total" => "Insgesamt");
        			
				// HTML Tabelle erstellen
            			$HTML = "<table border='1'>";
            
				$HTML .= "<thead>";
				$HTML .= "<tr>";
				$HTML .= "<th>Messung</th>";
				$HTML .= "<th>Start</th>";
				$HTML .= "<th>Ende</th>";
				$HTML .= "<th>Samples durchgeführt</th>";
				$HTML .= "<th>Samples verworfen</th>";
				$HTML .= "<th>Mode AC</th>";
				$HTML .= "<th>Mode S</th>";
				$HTML .= "<th>Schlecht</th>";
				$HTML .= "<th>Unbekannte ICAO</th>";
				$HTML .= "</tr>";
				$HTML .= "</thead>";
            			foreach ($StatisticArray as $Key => $Line){
                			$HTML .= "<tbody>";
                			$HTML .= "<tr>";
					$HTML .= "<th>$Line</th>";
					$Start = date('d.m.Y H:i', $Statistics->$Key->start);
					$HTML .= "<td>$Start</td>";       
					$End = date('d.m.Y H:i', $Statistics->$Key->end);
					$HTML .= "<td>$End</td>";
					$SamplesProcessed = $Statistics->$Key->local->samples_processed;
					$HTML .= "<td><p align='right'>$SamplesProcessed</td>";       
					$SamplesDropped = $Statistics->$Key->local->samples_dropped;
					$HTML .= "<td><p align='right'>$SamplesDropped</td>";
					$ModeAC = $Statistics->$Key->local->modeac;
					$HTML .= "<td><p align='right'>$ModeAC</td>";       
					$ModeS = $Statistics->$Key->local->modes;
					$HTML .= "<td><p align='right'>$ModeS</td>";
					$Bad = $Statistics->$Key->local->bad;
					$HTML .= "<td><p align='right'>$Bad</td>";
					$UnknownICAO = $Statistics->$Key->local->unknown_icao;
					$HTML .= "<td><p align='right'>$UnknownICAO</td>";
            			}
			    	$HTML .= "</tr>";
			    	$HTML .= "</tbody>";

			    	$HTML .= "</table>";
            			
				If (GetValueString($this->GetIDForIdent("Statistics")) <> $HTML) {
    					SetValueString($this->GetIDForIdent("Statistics"), $HTML);
				}
        		}   
    		}
	}
}
?>
