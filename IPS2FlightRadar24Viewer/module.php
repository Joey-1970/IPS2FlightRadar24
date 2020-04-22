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
			$this->Aircrafts();
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
            			$StatisticArray = array("last1min" => "1 Minute", "last5min" => "5 Minuten", "last15min" => "15 Minuten", "total" => "Insgesamt");
        			
				// HTML Tabelle erstellen
            			$HTML = "<table border='1'>";
            
				$HTML .= "<thead>";
				$HTML .= "<tr>";
				$Headline = "Stand:<br>".date('d.m.Y H:i', $Statistics->latest->end);
				$HTML .= "<th>$Headline</th>";
				$HTML .= "<th>Start</th>";
				$HTML .= "<th>Samples<br>durchgeführt</th>";
				$HTML .= "<th>Samples<br>verworfen</th>";
				$HTML .= "<th>Mode AC</th>";
				$HTML .= "<th>Mode S</th>";
				$HTML .= "<th>Schlecht</th>";
				$HTML .= "<th>Unbekannte<br>ICAO</th>";
				$HTML .= "<th>Signal (dB)</th>";
				$HTML .= "<th>Noise (dB)</th>";
				$HTML .= "<th>Peak Signal (dB)</th>";
				$HTML .= "<th>Starke<br>Signale</th>";
				$HTML .= "</tr>";
				$HTML .= "</thead>";
            			foreach ($StatisticArray as $Key => $Line){
                			$HTML .= "<tbody>";
                			$HTML .= "<tr>";
					$HTML .= "<th align='left'>$Line</th>";
					$Start = date('H:i', $Statistics->$Key->start);
					$HTML .= "<td>$Start</td>";       
					$SamplesProcessed = $Statistics->$Key->local->samples_processed;
					$HTML .= "<td align='right'>$SamplesProcessed</td>";     
					$SamplesDropped = $Statistics->$Key->local->samples_dropped;
					$HTML .= "<td align='right'>$SamplesDropped</td>";
					$ModeAC = $Statistics->$Key->local->modeac;
					$HTML .= "<td align='right'>$ModeAC</td>";       
					$ModeS = $Statistics->$Key->local->modes;
					$HTML .= "<td align='right'>$ModeS</td>";
					$Bad = $Statistics->$Key->local->bad;
					$HTML .= "<td align='right'>$Bad</td>";
					$UnknownICAO = $Statistics->$Key->local->unknown_icao;
					$HTML .= "<td align='right'>$UnknownICAO</td>";
					If (isset($Statistics->$Key->local->signal)) {
						$Signal = $Statistics->$Key->local->signal;
						$HTML .= "<td align='right'>$Signal</td>";
					}
                			else {
                    				$HTML .= "<td align='right'>---</td>";
                			}
					$Noise = $Statistics->$Key->local->noise;
					$HTML .= "<td align='right'>$Noise</td>";
					If (isset($Statistics->$Key->local->peak_signal)) {
						$PeakSignal = $Statistics->$Key->local->peak_signal;
						$HTML .= "<td align='right'>$PeakSignal</td>";
					}
                			else {
                    				$HTML .= "<td align='right'>---</td>";
                			}
					$StrongSignals = $Statistics->$Key->local->strong_signals;
					$HTML .= "<td align='right'>$StrongSignals</td>";
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
	    
	private function Aircrafts()
	{
    		$IP = $this->ReadPropertyString("IP");
    		$AircraftsJSON = file_get_contents('http://'.$IP.'/dump1090/data/aircraft.json');
    		If ($AircraftsJSON === false) {
			$this->SendDebug("Aircrafts", "Fehler beim Lesen der Datei!", 0);
    		}
    		else {
        		$Aircrafts = array();
        		$Aircrafts = json_decode($AircraftsJSON);
        		If (is_object($Aircrafts) == false) {
				$this->SendDebug("Aircrafts", "Datei ist kein Array!", 0);
        		}
        		else {
            			//$StatisticArray = array("last1min" => "Letzte Minute", "last5min" => "Letzte 5 Minuten", "last15min" => "Letzte 15 Minuten", "total" => "Insgesamt");
            			      
               
            			$HTML = "<table border='1'>";
            
			    	$HTML .= "<thead>";
			    	$HTML .= "<tr>";
			    	$Headline = "Stand:<br>".date('d.m.Y H:i', $Aircrafts->now)."<br>".date('d.m.Y H:i', $Aircrafts->messages);
				$HTML .= "<th>$Headline</th>";
			    	$HTML .= "</tr>";
            			$HTML .= "</thead>";
				/*
            			foreach ($StatisticArray as $Key => $Line){
					$HTML .= "<tbody>";
					$HTML .= "<tr>";
					$HTML .= "<th>$Line</th>";
					$Start = date('d.m.Y H:i:s', $Statistics->$Key->start);
					$HTML .= "<td>$Start</td>";       
					$End = date('d.m.Y H:i:s', $Statistics->$Key->end);
					$HTML .= "<td>$End</td>";
					$SamplesProcessed = $Statistics->$Key->local->samples_processed;
					$HTML .= "<td><p align='right'>$SamplesProcessed</td>";       
					$SamplesDropped = $Statistics->$Key->local->samples_dropped;
					$HTML .= "<td><p align='right'>$SamplesDropped</td>";
					$ModeAC = $Statistics->$Key->local->modeac;
					$HTML .= "<td>$ModeAC</td>";       
					$ModeS = $Statistics->$Key->local->modes;
					$HTML .= "<td>$ModeS</td>";
					$Bad = $Statistics->$Key->local->bad;
					$HTML .= "<td>$Bad</td>";
					$UnknownICAO = $Statistics->$Key->local->unknown_icao;
					$HTML .= "<td>$UnknownICAO</td>";
					If (isset($Statistics->$Key->local->signal)) {
					$Signal = $Statistics->$Key->local->signal;
					$HTML .= "<td>$Signal</td>";
					}
					else {
					    $HTML .= "<td>---</td>";
					}
            			}
				*/
			    	$HTML .= "</tr>";
			    	$HTML .= "</tbody>";

            			$HTML .= "</table>";
            
    
            			If (GetValueString($this->GetIDForIdent("Aircrafts")) <> $HTML) {
    					SetValueString($this->GetIDForIdent("Aircrafts"), $HTML);
				}
        		}   
    		}
	}
	/*
	messages: the total number of Mode S messages processed since dump1090 started.
	aircraft: an array of JSON objects, one per known aircraft. Each aircraft has the following keys. Keys will be omitted if data is not available.
	hex: the 24-bit ICAO identifier of the aircraft, as 6 hex digits. The identifier may start with '~', this means that the address is a non-ICAO address (e.g. from TIS-B).
	squawk: the 4-digit squawk (octal representation)
	flight: the flight name / callsign
	lat, lon: the aircraft position in decimal degrees
	nucp: the NUCp (navigational uncertainty category) reported for the position
	seen_pos: how long ago (in seconds before "now") the position was last updated
	altitude: the aircraft altitude in feet, or "ground" if it is reporting it is on the ground
	vert_rate: vertical rate in feet/minute
	track: true track over ground in degrees (0-359)
	speed: reported speed in kt. This is usually speed over ground, but might be IAS - you can't tell the difference here, sorry!
	messages: total number of Mode S messages received from this aircraft
	seen: how long ago (in seconds before "now") a message was last received from this aircraft
	rssi: recent average RSSI (signal power), in dbFS; this will always be negative.
	*/
}
?>
