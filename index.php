<?
//Koncov� bod u��vate�a, poskytuj�ci vo form�te JavaScript Object Notation (JSON) po�adovan� d�ta z poskytnutej datab�zy
$endPoint = new EndPoint();
echo $endPoint->command();

class EndPoint {
    
    //Pri laden� k�du protect = false, inak sa rovn� true
    const protect   = false;
    
    //Pri laden� k�du m��eme zada� pracovn� SQL
    const SQL       = 'SELECT * FROM 0vr2r_sysUserAccounts, 0vr2r_sysVisitorsIn';

    //Deklar�cia tabu�ky pre kontrolu povolenia pr�stupu vzdialen�ho ��astn�ka
    const accessUsersTable  = '000aaa_USER_ENDPOINT';
    const prefixUsersTable  = 'uep';
    
    //Deklar�cia �trukt�ry tabu�ky pre kontrolu povolenia pr�stupu vzdialen�ho ��astn�ka
    const accessUsersFields = array('name',    'pass',    'serial',  'active', 'validUntil', 'jurisdiction');  
    const accessUsersTypes  = array('VARCHAR(30)', 'VARCHAR(30)', 'VARCHAR(30)', 'BOOLEAN', 'DATE', 'SMALLINT');
    const accessUsersRecord = array('name'=>'userEndPoint', 'pass'=>'enigma', 'serial'=>'123-ABC', 'active'=>'1', 'jurisdiction'=>0);

    //Kon�truktor triedy
    function __construct() {

        //Defin�cia akceptovate�n�ho dia�kov�ho pr�stupu u��vate�a pre tento koncov� bod
        header("Access-Control-Allow-Origin: http://localhost:3000");    
        
        //Pri laden� k�du vytvor�me do�asn� "protection"
        if(!self::protect) {$_POST["protection"] = 'ABNet';}
        
        //Pri laden� k�du vytvor�me do�asn� parametre pre pr�stup
        if(!self::protect) {$_POST["user"] = 'userEndPoint';}
        if(!self::protect) {$_POST["pass"] = 'enigma';}
        if(!self::protect) {$_POST["serial"] = '123-ABC';}
        
        
        //PHP8+ v ka�dom pr�pade vy�aduje deklar�ciu POST premenn�ch aj ke� ako pr�zdny re�azec
        if(!isSet($_POST["protected"])) $_POST["protected"] = '';
        
        //Pri laden� k�du ak nem�me poskytnut� SQL nastav�me pracovn� SQL
        if(!self::protect) {if(!isSet($_POST["SQL"])) $_POST["SQL"] = self::SQL;}
        if(!self::protect && strLen(Trim($_POST["SQL"]))==0) {$_POST["SQL"] = self::SQL;}

        //Ak nie sme v re�ime ladenia, a klient je in� ako "Access-Control-Allow-Origin"
        //Funk�nos� kodu bude zastaven�
        if(!isSet($_POST["protection"])) {die();} else {if($_POST["protection"]!='ABNet') {die();}}

        //Zist�me parametre pre pripojenie sa ku datab�ze
        include('dbAccess.php');
        
        //Pripoj�me sa na poskytnut� datab�zu pod�a zisten�ch parametrov
        $this->con = @MySQLi_connect($this->dbLocal, $this->dbLogin, $this->dbPass,  $this->dbName);

        //Ak nastala v prip�jan� na datab�zu chyba, a poskytnut� datab�za nie je dostupn� 
        if (mysqli_connect_errno()) {
            //koncov� bod vr�ti re�azec reprezentuj�ci objekt s chybovou spr�vou v polo�ke "error"
            $this->con = '[{"error":"'.mysqli_connect_error().'"}]'; 
        } else {
            //Kontrola �i vzdialen� ��asn�k m� opr�vnenie aby prevzal objekt 
            //s po�adovan�mi  inform�ciami prevzat�mi z datab�zy
            $this->userAccess();
        }
        
    }
    
    //Pod�a SQL pr�kazu zavol�me pr�slu�n� priv�tnu met�du triedy na spracovanie SQL
    public function command() {
    
        //Ak nastala v prip�jan� na datab�zu chyba, a poskytnut� datab�za nie je dostupn� 
        //koncov� bod vr�ti re�azec reprezentuj�ci objekt s chybovou spr�vou v polo�ke "error"
        if(getType($this->con) == 'string') {return $this->con;}
            
        //Zavol�me priv�tnu met�du pr�slu�n� k spracovaniu aktu�lneho SQL pr�kazu
        eval('$JSON = $this->'.$this->isSQLCommand().'();');
        
        //Vr�time z�skan� Javascript Object Notation
        return $JSON;
    }
    
    //Priv�tna met�da triedy spracuje SQL pr�kaz SELECT[...]
    private function select() {
    
        $JSON = '[]';
        //Inicializujem pole do ktor�ho na��tam �trukt�ry tabuliek ako ich pou��va doru�en� SQL
        $aStructure = array();
        
        //Listujem tabu�ky z doru�en�ho SQL pr�kazu
        foreach($this->getTables() as $tableName) {
            //a �trukt�ry z tabuliek z doru�en�ho SQL pr�kazu prid�vam do po�a $aStructure 
            $aStructure = $this->structure($tableName, $aStructure);
        }

        //Na��tam obsah tabuliek parametrom poskytnut�ho SQL pr�kazu
        if($records = MySQLi_query($this->con, $_POST["SQL"])) {
            //Za�iatok JSON v r�mci re�azca
            $JSON = '[';
            //Listujem v riadkoch tabuliek pod�a poskytnnt�ho SQL
            while($line = MySQLi_Fetch_Array($records, MYSQLI_ASSOC))   {
                //Za�iatok vety v JSON v r�mci re�azca
                $JSON .= '{';
                foreach($aStructure as $field) {
                    //Vklad�m do JSON nov� polo�ku s �dajom prevzat�m z datab�zy 
                    $JSON .= '"'.$field.'":"'.$line[$field].'",';
                }
                //Koniec vety v JSON v r�mci re�azca s odrezan�m poslednej �iarky
                $JSON = subStr($JSON, 0, strLen($JSON)-1);
                $JSON .= '},';
            }
            //Koniec obsahu JSON objektu v r�mci re�azca s odrezan�m poslednej �iarky
            $JSON = subStr($JSON, 0, strLen($JSON)-1);
            $JSON .= ']';
        }

        //JSON objekt s po�adovan�mi �dajmi z datab�zy t�to met�da triedy vr�ti pre odovzdanie 
        //vzdialen�mu u��vate�ovi
        return $JSON;
    }
    
    //Priv�tna met�da zist�, �i SQL pr�kaz je pre koncov� bod spravovate�n�
    private function isSQLCommand() {
        //Do premennej $param na��ta pr�kaz z SQL
        $param = strToLower(trim(subStr(trim($_POST["SQL"]), 0, strPos($_POST["SQL"], ' '))));
        
        //Ak zisten� pr�kaz z SQL je koncov� bod schopn� spracova�, vr�ti n�zov na pr�slu�nej to priv�tnej met�dy triedy
        $command = match($param) {
            'select', 'insert'  => $param,
            default => 'emptyObject',
        };
        
        //Vr�ti n�zov na pr�slu�nej to priv�tnej met�dy triedy, alebo triedy ktor� vr�ti pr�zdny objekt
        return($command);        
    }
    
    //Ak SQL neobsahuje pr�kaz akceptovate�n� pre koncov� bod, touto met�dou vr�ti pr�zdny objekt 
    private function emptyObject() {return '[]';}
    
    //Vr�ti pole s n�zvami tabuliek, ako ich obsahuje koncov�mu bodu poskytnut� SQL pr�kaz
    private function getTables() {
    
        //Zist�m kde sa v SQL nach�dza rezervovan� v�raz FROM
        $from = strPos(strToLower($_POST["SQL"]), ' from ');
        
        //Ak rezervovan� v�raz FROM v SQL nen�jdem, vr�tim pr�zdne pole
        if(!$from) return array();
        
        //Ostr�nim �as� SQL pr�kazu pred defin�ciou n�zvov tabuliek
        $SQL = substr($_POST["SQL"], $from+5, strLen(Trim($_POST["SQL"])));

        //Zost�vaj�ci SQL re�azec premen�m na pole s n�zvami tabuliek a zvy�n�m nepotrebn�m k�dom
        $aTables = explode(',', $SQL);
        
        //Inicializujem n�vratov� pole
        $returnTables = array();
        
        //Listujem zisten� n�zy tabuliek aj s prebyto�n�m k�dom
        foreach($aTables as $table) {
            //Ore�em n�zov tabu�ky ak m� medzery
            $table = trim($table);
            
            //Zist�m �i v n�zne tabu�ky neostala medzera, �o by znamenalo �e n�zov obsahuje prebyto�n� k�d
            $space = strPos($table, ' ');
            
            //Ak v n�zve tabu�ky prebyto�n� k�d ostal zachovan� odre�em ho
            if($space) $table = subStr($table, 0, $space+1);
            
            //Do n�vratov�ho po�a prid�m �ist� n�zov tabu�ky bez ru�iv�ch znakov
            $returnTables[] = $table;
        }

        //Aktu�lna met�da vr�ti pole so spracovan�mi n�zvami tabuliek ako ich obsahoval SQL 
        //poskytnut� koncov�mu bodu
        return $returnTables;
    }

    //Priv�tna met�da na��ta n�zvy polo�iek zo �trukt�ry tabu�ky v parametri, prid� ich do paramera $aStucture
    //a tento parameter vr�ti 
    private function structure($table, $aStucture=array()) {

        //Vytvor�m SQL pr�kaz pre na��tanie prv�ho riadku z SQL tabu�ky s n�zvom doru�en�m v parametri met�dy
        $SQL = "SELECT * FROM $table LIMIT 1";

        //Na��tam prv� riadok z SQL tabu�ky  s n�zvom doru�en�m v parametri met�dy
        if($records = $this->con->query($SQL)) {
            //Na��tam +strukt=uru tabu�ky  s n�zvom doru�en�m v parametri met�dy
            $structure = $records->fetch_fields();
    
            //Listujem strukt�ru tabu�ky z parametra
            foreach ($structure as $field) {
                //a aktu�lny n�zov po�a tabu�ky vklad�m do n�vratov�ho po�a
                $aStucture[] = $field->name;
            }
        } else {
            // to do   
        }
        
        //Vr�tim n�vratov� pole s n�zvami jehnotliv�ch polo�iek �trukt�ry tabu�ky
        return $aStucture;
    }
    
    //Met�da triedy pre pr�cu s pr�stupovou tabu�kou 
    private function userAccess() {
    
        //Priprav�m SQL pr�kaz pre na��tanie tabu�ky s pr�s�ubmi povolen�ch pr�stupov
        //to do doplni� podmienku z POST parametrov
        //name',    'pass',    'serial',  'active', 'validUntil', 'jurisdiction'
        $prx = self::prefixUsersTable;
        $SQL = 'SELECT * FROM '.self::accessUsersTable.' WHERE '   .$prx.'_name="'.$_POST["user"].'" && '.
                                                                    $prx.'_pass="'.$_POST["pass"].'" && '.
                                                                    $prx.'_serial="'.$_POST["serial"].'" && '.
                                                                    $prx.'_active=1';
        //Ak na��tanie prebehne v poriadku
        if($records = $this->con->query($SQL)) {                    
        
            //Zist�m �i je vzdialen� u��vate� opr�vnen� pou�iva� tento koncov� bod
            if(MySQLi_Num_Rows($records)>0) {
                //Ak �no
                echo ' OK ';
            } else {
                //Ak nie
                echo ' ACCESS DENIED ';
            }

        } else {
            echo 'ERROR ACCESS';
            //Ak sa tabu�ku s pr�stupov�mi pr�vami nepodarilo na��ta�, pok�sim sa ju vytvori�
            $isCreate = $this->createUserAccess();
            
            //Ak som ju vytvoril a som b re�ime ladenia zdrojov�ho k�du
            if(!self::protect && $isCreate) {
                
                //Inicializujem triedu s intern�mi SQL pr�kazmi
                // to do: upravi� parametre medzi kon�truktorom a parametrami met�d
                $internalSQL = new InternalSQL( array(  'con'=>$this->con,
                                                        'protect'=>self::protect,
                                                        'tableName'=>self::accessUsersTable,
                                                        'aInsert'=>self::accessUsersRecord,
                                                        'prefix'=>self::prefixUsersTable));
                
                //Ak sa podar� vlo�i� prv� z�znam vytvoren� syst�mom rekurz�vne op� zavol�m t�to met�du 
                if($internalSQL->insert()) $this->userAccess();
            }
            return false;
       }
    }
    
    //Priv�tne met�da, ktor� vytvor� tabu�ku pr�stupov kku koncov�mu bodu
    private function createUserAccess() {
        
        //Nazov syst�movej tabu�ky povolen�ch pr�stupov na��tame z kon�tanty triedy
        $aTable     = array(self::accessUsersTable);
        
        //Polia n�zvov polo�iek a ich typov v syst�movej tabu�ke tie� na��tame z kon�t�nt triedy
        $aFields    = self::accessUsersFields;
        $aTypes     = self::accessUsersTypes;
        
        //Inicializujem triedu s intern�mi SQL pr�kazmi
        // to do: upravi� parametre medzi kon�truktorom a parametrami met�d
        $internalSQL = new InternalSQL( array(  'con'=>$this->con,
                                                'protect'=>self::protect,
                                                'aTable'=>$aTable,
                                                'aFields'=>$aFields,
                                                'aTypes'=>$aTypes,
                                                'prefix'=>self::prefixUsersTable));        
        
        //Z triedy intern�ch SQL pr�kazov zavol�me met�du pre vytvorenie syst�movej tabu�ky pr�stupov
        return $internalSQL->createTable();
    }
}

//Trieda s met�dami pre intern� tvorbu SQL pr�kazov a ich pou�itie
class InternalSQL {
    
    function __construct($aParams) {

        //V kon�truktore na��tame parametre triedy z pola s kl��mi reprezentuj�cimi n�zvy premenn�ch
        //inicializujem ich ako intern� premenn� triedy
        foreach(array_keys($aParams) as $key) eval("\$this->".$key." = \$aParams[\$key];");
    }
    
    //Intern� met�da vytv�raj�ca nov� MySQL tabu�ku
    public function createTable() {     //to do: dopracova� parametre do pola s kl��mi reprezentuj�cimi n�zvy premenn�ch

        //Kontrola parametrov, �i s� v poriadku a fat�lne nezastavia chod met�dy
        if( count(self::arrayControl($this->aTable)) == 0 ||
            count(self::arrayControl($this->aFields)) == 0 ||
            count(self::arrayControl($this->aTypes)) == 0)  return false;

        if(count(self::arrayControl($this->aFields)) != count(self::arrayControl($this->aTypes)))
            return false;
            
        //Inicializujeme nov� kon�truktor pre pracu s prefixom tabuliek
        $prefix = new Prefix();
        
        //Zist�me po�et nov�chh polo�iek, ktor� bude obsahova� nov� tabu�ka    
        $countFields = count(self::arrayControl($this->aFields));
        
        //Pok�sime sa zisti� z n�zvov polo�iek nastaven� prefix novej tabu�ky
        $prx = $prefix->getPrefix($this->aFields[0]);
        //Ak sme ho nezistili preberieme ho  z parametra kon�truktora triedy
        if(strLen(Trim($prx))==0) $prx = $this->prefix;

        //Pracovn� premenn� pre vytvorenie jednozna�n�ho indexovan�ho k���a
        $indexKey = '';
        
        //Za�iatok re�azca prezentuj�ceho SQL pr�kaz na vytvorenie novej MySQL tabu�ky
        $SQL = "CREATE TABLE IF NOT EXISTS ".$this->aTable[0]."(";
        
        //Premenn� indikuj�ca prv� z�znam po�a polo�iek novej MySQL tabu�ky 
        $first = true;
        
        //Listovanie z�znamov po�a polo�iek novej MySQL tabu�ky 
        for($index=0;$index<$countFields;++$index) {
            //Ak sme v prvom kole cyklu listovania
            if($first) {
                //Prid�me prv� polo�ku id s prefixom, ktor� bude unik�tna a indexovan� v datab�ze
                $SQL .= $this->prefix."_id BIGINT unsigned AUTO_INCREMENT NOT NULL, ";    
                
                //Nastav�me premenn� pre vytvorenie jednozna�n�ho indexovan�ho k���a
                $indexKey = ",  PRIMARY KEY (".$this->prefix."_id)";
                
                //�al�ie kolo tohto cyklu u� nebude prv�
                $first = !$first;
            }
            //Vlo��me z parametra pr�slu�n� nov� polo�ku k vytv�ranej tabu�ke, jej typ a �al�ie vlastnosti
            $SQL .= $prefix->withPrefix($this->aFields[$index], $prx)." ".$this->aTypes[$index]." COLLATE utf8_slovak_ci NOT NULL, ";
        }
        
        //Ore�eme posledn� �iarku, prid�me premenn� s obsahom vz�ahuj�cim sa na indexvan� 
        //a unik�tnu polo�ku a SQL Pr�kaz uzavrieme
        $SQL = subStr($SQL, 0, strLen(Trim($SQL))-1)." $indexKey)";
        
        //Ku SQL pr�kazu na vyvorenie tabu�ky dopln�me �al�ie vlastnosti
        $SQL .= " ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_slovak_ci AUTO_INCREMENT=1;";

        //Ak sa podar� tabu�ku vytvori�, t�to met�da vr�ti true
        if ($this->con->query($SQL) === true) return true; else {
            
            //Ak nie ... vr�ti false
            echo "ERROR ::: ".$this->con->error;
            return false;
        }
    
    }
   
    //Intern� met�da prid�vaj�ca nov� z�znam do existuj�cej MySQL tabu�ky
    public function insert() {

        //Kontrola parametrov, �i s� v poriadku a fat�lne nezastavia chod met�dy
        if(getType($this->tableName)!='string') return false;
        if(strLen(Trim($this->tableName))==0) return false;
        
        if(getType($this->aInsert)!='array') return false;
        if(count($this->aInsert)==0) return false;
        
        foreach(array_keys($this->aInsert) as $key) {
            if(strLen(Trim($key))==0) return false;
            break;
        }
   
        //Inicializujeme nov� kon�truktor pre pracu s prefixom tabuliek
        $prefix = new Prefix();

        //Premenn� indikuj�ca prv� z�znam po�a polo�iek novej MySQL tabu�ky        
        $first = true;
        
        //Za�iatok re�azca prezentuj�ceho SQL pr�kaz na vlo�enie nov�ho z�znamu 
        //do existuj�cej  MySQL tabu�ky
        $SQL = "INSERT INTO $this->tableName (";
        
        //Listovanie z�znamov s ktor�mi m� SQL pr�kaz v MySQL tabu�ke pracova�
        foreach(array_keys($this->aInsert) as $key) {
            
            //Ak sme v prvom kole cyklu listovania
            if($first) {

                //Pok�sime sa zisti� z n�zvov polo�iek nastaven� prefix novej tabu�ky
                $prx = $prefix->getPrefix($key); 
                //Ak sme ho nezistili preberieme ho  z parametra kon�truktora triedy
                if(strLen(Trim($prx))==0) $prx = $this->prefix;

                //�al�ie kolo tohto cyklu u� nebude prv�
                $first = !$first;}
                
            //Postupne na��tam z po�a v parametri v�etky n�zvy polo�iek MySQL tabu�ky
            //ktor� bud� pou��van� pri prid�van� nov�ho z�znamu
            $SQL .= $prefix->withPrefix($key, $prx).",";
        }

        //Ideme na��ta� hodnoty do nov�ho z�znamu MySQL tabu�ky
        $SQL = subStr($SQL, 0, strLen(Trim($SQL))-1).") VALUES(";
        foreach(array_keys($this->aInsert) as $key) $SQL .= "'".$this->aInsert[$key]."',";
    
        //Ore�eme posledn� �iarku a SQL pr�kaz na pridanie z�znamu uzavrieme z�tvorkou
        $SQL = subStr($SQL, 0, strLen(Trim($SQL))-1).")";

        //Ak bol z�znam �spe�ne pridan� met�da vr�ti true
        if ($this->con->query($SQL) === true) return true; else {
            //Ak nie ...  vr�ti false
            echo "ERROR ::: ".$this->con->error;
            return false;
        }
   }
   
   //to do: Dopracova� koment�re
   private function arrayControl($param) {
        if(getType($param)=='string') {
            if(strLen(Trim($param))==0) return array();
            return array($param);
        }
        
        if(getType($param)!='array') return array();
        return $param;
    }
}


//to do: Dopracova� koment�re
class Prefix {
    
    function __construct() {}

    public function getPrefix($fieldName) {
        if(strPos($fieldName,'_')) return subStr($fieldName, 0, strPos($fieldName,'_')-1);
        return '';
    }
    
    public function withPrefix($fieldName, $prefix='') {
        
        if(strLen(Trim($prefix))>0) $prefix = $prefix."_";
        if(!strPos($fieldName,'_')) return "$prefix$fieldName";
        return $fieldName;
    }

}
