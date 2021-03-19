<?
//Koncový bod užívate¾a, poskytujúci vo formáte JavaScript Object Notation (JSON) požadované dáta z poskytnutej databázy
$endPoint = new EndPoint();
echo $endPoint->command();

class EndPoint {
    
    //Pri ladení kódu protect = false, inak sa rovná true
    const protect   = false;
    
    //Pri ladení kódu môžeme zada pracovný SQL
    const SQL       = 'SELECT * FROM 0vr2r_sysUserAccounts, 0vr2r_sysVisitorsIn';

    //Konštruktor triedy
    function __construct() {
        //Definícia akceptovate¾ného dia¾kového prístupu užívate¾a pre tento koncový bod
        header("Access-Control-Allow-Origin: http://localhost:3000");    
        
        //Pri ladení kódu vytvoríme doèasný "protection"
        if(!self::protect) {$_POST["protection"] = 'ABNet';}

        //PHP8+ v každom prípade vyžaduje deklaráciu POST premenných aj keï ako prázdny reazec
        if(!isSet($_POST["protected"])) $_POST["protected"] = '';
        
        //Pri ladení kódu ak nemáme poskytnuté SQL nastavíme pracovné SQL
        if(!self::protect) {if(!isSet($_POST["SQL"])) $_POST["SQL"] = self::SQL;}
        if(!self::protect && strLen(Trim($_POST["SQL"]))==0) {$_POST["SQL"] = self::SQL;}

        //Ak nie sme v režime ladenia, a klient je iný ako "Access-Control-Allow-Origin"
        //Funkènos kodu bude zastavená
        if(!isSet($_POST["protection"])) {die();} else {if($_POST["protection"]!='ABNet') {die();}}

        //Zistíme parametre pre pripojenie sa ku databáze
        include('dbAccess.php');
        
        //Pripojíme sa na poskytnutú databázu pod¾a zistených parametrov
        $this->con = @MySQLi_connect($this->dbLocal, $this->dbLogin, $this->dbPass,  $this->dbName);

        //Ak nastala v pripájaní na databázu chyba, a poskytnutá databáza nie je dostupná 
        if (mysqli_connect_errno()) {
            //koncový bod vráti reazec reprezentujúci objekt s chybovou správou v položke "error"
            $this->con = '[{"error":"'.mysqli_connect_error().'"}]'; 
        }
    }
    
    //Pod¾a SQL príkazu zavoláme príslušnú privátnu metódu triedy na spracovanie SQL
    public function command() {
    
        //Ak nastala v pripájaní na databázu chyba, a poskytnutá databáza nie je dostupná 
        //koncový bod vráti reazec reprezentujúci objekt s chybovou správou v položke "error"
        if(getType($this->con) == 'string') {return $this->con;}
            
        //Zavoláme privátnu metódu príslušnú k spracovaniu aktuálneho SQL príkazu
        eval('$JSON = $this->'.$this->isSQLCommand().'();');
        
        //Vrátime získaný Javascript Object Notation
        return $JSON;
    }
    
    //Privátna metóda triedy spracuje SQL príkaz SELECT[...]
    private function select() {
    
        $JSON = '[]';
        //Inicializujem pole do ktorého naèítam štruktúry tabuliek ako ich používa doruèené SQL
        $aStructure = array();
        
        //Listujem tabu¾ky z doruèeného SQL príkazu
        foreach($this->getTables() as $tableName) {
            //a štruktúry z tabuliek z doruèeného SQL príkazu pridávam do po¾a $aStructure 
            $aStructure = $this->structure($tableName, $aStructure);
        }

        //Naèítam obsah tabuliek parametrom poskytnutého SQL príkazu
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
    
    //Privátna metóda zistí, èi SQL príkaz je pre koncový bod spravovate¾ný
    private function isSQLCommand() {
        //Do premennej $param naèíta príkaz z SQL
        $param = strToLower(trim(subStr(trim($_POST["SQL"]), 0, strPos($_POST["SQL"], ' '))));
        
        //Ak zistený príkaz z SQL je koncový bod schopný spracova, vráti názov na príslušnej to privátnej metódy triedy
        $command = $param == 'select' ? 'select' : 'emptyObject';
        
        //Vráti názov na príslušnej to privátnej metódy triedy, alebo triedy ktorá vráti prázdny objekt
        return($command);        
    }
    
    //Ak SQL neobsahuje príkaz akceptovate¾ný pre koncový bod, touto metódou vráti prázdny objekt 
    private function emptyObject() {return '[]';}
    
    //Vráti pole s názvami tabuliek, ako ich obsahuje koncovému bodu poskytnutý SQL príkaz
    private function getTables() {
    
        //Zistím kde sa v SQL nachádza rezervovaný výraz FROM
        $from = strPos(strToLower($_POST["SQL"]), ' from ');
        
        //Ak rezervovaný výraz FROM v SQL nenájdem, vrátim prázdne pole
        if(!$from) return array();
        
        //Ostránim èas SQL príkazu pred definíciou názvov tabuliek
        $SQL = substr($_POST["SQL"], $from+5, strLen(Trim($_POST["SQL"])));

        //Zostávajúci SQL reazec premením na pole s názvami tabuliek a zvyšným nepotrebným kódom
        $aTables = explode(',', $SQL);
        
        //Inicializujem návratové pole
        $returnTables = array();
        
        //Listujem zistené názy tabuliek aj s prebytoèným kódom
        foreach($aTables as $table) {
            //Orežem názov tabu¾ky ak má medzery
            $table = trim($table);
            
            //Zistím èi v názne tabu¾ky neostala medzera, èo by znamenalo že názov obsahuje prebytoèný kód
            $space = strPos($table, ' ');
            
            //Ak v názve tabu¾ky prebytoèný kód ostal zachovaný odrežem ho
            if($space) $table = subStr($table, 0, $space+1);
            
            //Do návratováho po¾a pridám èistý názov tabu¾ky bez rušivých znakov
            $returnTables[] = $table;
        }

        //Aktuálna metóda vráti pole so spracovanými názvami tabuliek ako ich obsahoval SQL 
        //poskytnutý koncovému bodu
        return $returnTables;
    }

    //Privátna metóda naèíta názvy položiek zo štruktúry tabu¾ky v parametri, pridá ich do paramera $aStucture
    //a tento parameter vráti 
    private function structure($table, $aStucture=array()) {

        //Vytvorím SQL príkaz pre naèítanie prvého riadku z SQL tabu¾ky s názvom doruèeným v parametri metódy
        $SQL = "SELECT * FROM $table LIMIT 1";
        
        
        //Naèítam prvý riadok z SQL tabu¾ky  s názvom doruèeným v parametri metódy
        $records = $this->con->query($SQL);
        
        //Naèítam +strukt=uru tabu¾ky  s názvom doruèeným v parametri metódy
        $structure = $records->fetch_fields();

        //Listujem struktúru tabu¾ky z parametra
        foreach ($structure as $field) {
            //a aktuálny názov po¾a tabu¾ky vkladám do návratového po¾a
            $aStucture[] = $field->name;
        }
      
        //Vrátim návratové pole s názvami jehnotlivých položiek štruktúry tabu¾ky
        return $aStucture;
    }
}
