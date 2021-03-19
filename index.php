<?
//Koncov� bod u��vate�a, poskytuj�ci vo form�te JavaScript Object Notation (JSON) po�adovan� d�ta z poskytnutej datab�zy
$endPoint = new EndPoint();
echo $endPoint->command();

class EndPoint {
    
    //Pri laden� k�du protect = false, inak sa rovn� true
    const protect   = false;
    
    //Pri laden� k�du m��eme zada� pracovn� SQL
    const SQL       = 'SELECT * FROM 0vr2r_sysUserAccounts, 0vr2r_sysVisitorsIn';

    //Kon�truktor triedy
    function __construct() {
        //Defin�cia akceptovate�n�ho dia�kov�ho pr�stupu u��vate�a pre tento koncov� bod
        header("Access-Control-Allow-Origin: http://localhost:3000");    
        
        //Pri laden� k�du vytvor�me do�asn� "protection"
        if(!self::protect) {$_POST["protection"] = 'ABNet';}

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
            $JSON = '[';
            while($line = MySQLi_Fetch_Array($records, MYSQLI_ASSOC))   {
                $JSON .= '{';
                foreach($aStructure as $field) {
                    $JSON .= '"'.$field.'":"'.$line[$field].'",';
                }
                $JSON = subStr($JSON, 0, strLen($JSON)-1);
                $JSON .= '},';
            }
            $JSON = subStr($JSON, 0, strLen($JSON)-1);
            $JSON .= ']';
        }

        return $JSON;
    }
    
    //Priv�tna met�da zist�, �i SQL pr�kaz je pre koncov� bod spravovate�n�
    private function isSQLCommand() {
        //Do premennej $param na��ta pr�kaz z SQL
        $param = strToLower(trim(subStr(trim($_POST["SQL"]), 0, strPos($_POST["SQL"], ' '))));
        
        //Ak zisten� pr�kaz z SQL je koncov� bod schopn� spracova�, vr�ti n�zov na pr�slu�nej to priv�tnej met�dy triedy
        $command = $param == 'select' ? 'select' : 'emptyObject';
        
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
        $records = $this->con->query($SQL);
        
        //Na��tam +strukt=uru tabu�ky  s n�zvom doru�en�m v parametri met�dy
        $structure = $records->fetch_fields();

        //Listujem strukt�ru tabu�ky z parametra
        foreach ($structure as $field) {
            //a aktu�lny n�zov po�a tabu�ky vklad�m do n�vratov�ho po�a
            $aStucture[] = $field->name;
        }
      
        //Vr�tim n�vratov� pole s n�zvami jehnotliv�ch polo�iek �trukt�ry tabu�ky
        return $aStucture;
    }
}
