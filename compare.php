
//!/usr/bin/php
<?php 
# This php script compares 2 mysql databases (source database / target database).After it generates sql script for synchronization structure.
# mysqli classes used
# Warning !!! --drop=YES : drop fields,tables in target DB.
class dbo 
{
  public $username="";
  public $pass="";
  public $host="";
  public $db="";
  public $port;
}

//echo phpversion().PHP_EOL;
$hmsg='Usage: php compare.php --from=user1:pass1@host1:[port1]/db1 --to=user2:pass2@host2:[port2]/db2 --alter=/tmp/alter.sql [--drop=YES]'.PHP_EOL;

if (($argc < 4) or eregi("-h|-\?", $argv[1]))
{
  die($hmsg);
}

$source_db=new dbo();
$dest_db=new dbo();
$dest_file="";
$is_drop='NO';

// parsing parameters
foreach ($argv as $item)
{
  
  if (preg_match("/^(--)([^=]+)/i", $item, $param_name))
  {
    $param_v=substr(strstr($item,'='),1);  //parameter value
    if ($param_v)
    {
      if ($b_db)  
      {
        unset($b_db); 
        $b_db=new dbo();
      }

      switch ($param_name[2])
      {
      case 'from';
      case 'to';
      {
        $i=strpos($param_v,':');
        if ($i) {$b_db->username=substr($param_v,0,$i); $param_v=substr($param_v,$i+1);}

        $i=strpos($param_v,'@');
        if ($i) {$b_db->pass=substr($param_v,0,$i); $param_v=substr($param_v,$i+1);}

        $i=strpos($param_v,':');
        if (!$i) { $i=strpos($param_v,'/');}
        if ($i)  {$b_db->host=substr($param_v,0,$i); $param_v=substr($param_v,$i+1);}

        $i=strpos($param_v,'/');        
        if ($i) {$b_db->port=substr($param_v,0,$i); $param_v=substr($param_v,$i+1);}

        if ($param_v) {$b_db->db=$param_v;}

        if ($param_name[2]==="from") 
        {
          $source_db=$b_db;
        } 
        else 
        {
          $dest_db=$b_db;
        }        
        break ;
      }
      case 'alter':
        $dest_file=$param_v;
        break;
      case 'drop':
        $is_drop=$param_v;
        break;
      }
    }
  }
}

if ((strlen(source_db.host)===0) or (strlen(dest_db.host)===0) or (strlen($dest_file)===0) or (strlen(source_db.user)===0) or (strlen(dest_db.user)===0) or (strlen($dest_file)===0))
{
  die ($hmsg);
}

// open dest db
if (!$mysqli_d=open_db($dest_db)) die('Can not open db ' .$dest_db->host.':'.$dest_db->port.'->'.$dest_db->db.PHP_EOL);

//open source db
if (!$mysqli_s=open_db($source_db)) die('Can not open db ' .$source_db->host.':'.$source_db->port.'->'.$source_db->db.PHP_EOL);

// open result file
if (!$fout = fopen($dest_file, 'w')) die("Can not open file $dest_file");

fwrite($fout,'use '.$dest_db->db.';'.PHP_EOL);


// check source DB
unset($table_source); 
$i=0;
if ($result_s = $mysqli_s->query('SHOW TABLES;')) 
{ 
//  List source tables
  if ($result_s->num_rows>0)
  {
    fwrite(STDOUT,'Get tables from source:' .$source_db->db.PHP_EOL);

    while($row_s = $result_s->fetch_array(MYSQLI_NUM))
    { 
      $i++;
      $table_source[$i]= $row_s[0];
//     list source table stru
      $query_string='DESCRIBE '.$table_source[$i].';';
      
      if ($result_st = $mysqli_s->query($query_string)) 
      {
        if ($result_st->num_rows>0)
        {
          $j=0;
          unset($table_source_t);
          while($row_st = $result_st->fetch_array(MYSQLI_NUM))
          { 
            $j++;
            $table_source_t[$j]= $row_st;  //store fields structure for table $table_source[$i]
            $val=$table_source_t[$j];
          }
        }
      }
      $result_st->close();
      // select table from dest db
      unset($sync_sql);
      unset($field_stru);
      fwrite(STDOUT,PHP_EOL.'----Check table:' .$table_source[$i].PHP_EOL);

      $query_string='DESCRIBE '.$table_source[$i].';';
      if (!$result_st = $mysqli_d->query($query_string))  // table not found
      {
        $sync_sql='';
        foreach ($table_source_t as $row_s)
        {
            $sync_sql.=PHP_EOL.field_string($row_s).',';   
        }
        $sync_sql='create table '.$table_source[$i].' ('.substr($sync_sql,0,-1).');';   // sql string "create table ..." created
        fwrite(STDOUT,'Create table ' .$source_db->db.'::'.$table_source[$i].PHP_EOL);

      }
      else
      {
        $j=0;
        unset($table_source_d);
        while($row_st = $result_st->fetch_array(MYSQLI_NUM))
        { 
          $j++;
          $table_source_d[$j]= $row_st;  //store field structure dest
          $val=$table_source_d[$j];
         }
         $result_st->close();         

         //search differens in tables;
         unset($pred_fields);
         foreach ($table_source_t as $row_s) // select 1 field from table source
         {
//           create stru fields
           $j=1;      // 1-fields not found , 2-different field, 0-fields equal
           foreach ($table_source_d as $row_d) // search field in dest table
           {
             if ($row_s[0]===$row_d[0])  // field found 
             {
               $diff=array_diff($row_s,$row_d);
               if ($diff)
               {
                 $j=2;      // fields is difference
               } 
               else
               {
                 $j=0;      // fields is equal
               } 
               break;
             }             
           }           
           switch ($j)
           {
           case 0:
             unset($field_stru);
             break;
           case 1:
             $field_stru='add '   .field_string($row_s);
             if ($pred_fields) $field_stru.=' AFTER '.$pred_fields;
             break;
           case 2:
             $field_stru='modify '.field_string($row_s);
             break;
           };
           if (($field_stru) and (!$sync_sql)) $sync_sql='';
           if ($field_stru) $sync_sql.=PHP_EOL.$field_stru.',';   
           $pred_fields=$row_s[0];
         }
        // sql string "alter table ..." created
        if ($sync_sql) 
        {
          $sync_sql='alter table '.$table_source[$i].' '.substr($sync_sql,0,-1).';';
        }
        else
        {
          fwrite(STDOUT,'Not found different'.PHP_EOL);
        }
      }
      
      if ($sync_sql)
      {
        fwrite(STDOUT,PHP_EOL.'sql='.$sync_sql.PHP_EOL);
        fwrite($fout,$sync_sql.PHP_EOL);
      }
    }
  }
  $result_s->close(); 
}



// reverse search for drop tables,field in target DB

if ($is_drop==='YES')
{
  if ($result_s = $mysqli_d->query('SHOW TABLES;')) 
  { 
    if ($result_s->num_rows>0)
    {
      fwrite(STDOUT,'Check tables in target DB=' .$dest_db->db.PHP_EOL);
      while ($row_s = $result_s->fetch_array(MYSQLI_NUM))
      { 
        fwrite(STDOUT,$row_s[0].PHP_EOL);
        unset($sync_sql);
        $i=array_search($row_s[0],$table_source);
        if (!$i) 
        {
          $sync_sql='DROP TABLE IF EXISTS '.$row_s[0].';';
        }
        else
        {
          unset($table_source_d);
          $query_string='DESCRIBE '.$table_source[$i].';';
          if ($result_dt = $mysqli_d->query($query_string)) 
          {
            if ($result_dt->num_rows>0)
            {
              if ($result_st = $mysqli_s->query($query_string))  //list fields in source table
              {
                $j=0;
                while($row_st = $result_st->fetch_array(MYSQLI_NUM))
                { 
                  $j++;
                  $table_source_d[$j]= $row_st[0];  //store fields structure for table $table_source[$i]
                }
              }  // store fields complete
              $result_st->close();


              while($row_st = $result_dt->fetch_array(MYSQLI_NUM))  // compare tables structure
              {
                $j=array_search($row_st[0],$table_source_d);
                if (!$j) 
                { 
                  if (!sync_sql) $sync_sql='';
                  $sync_sql.=PHP_EOL.'DROP '.$row_st[0].',';            
                }
              }
              if (strlen($sync_sql)>1) $sync_sql='alter table '.$table_source[$i].' '.substr($sync_sql,0,-1).';';   // sql string "alter table ..." created
            }
            $result_dt->close(); 
          }
        }

        
        if ($sync_sql)
        {
          fwrite(STDOUT,PHP_EOL.'sql='.$sync_sql.PHP_EOL);
          fwrite($fout,$sync_sql.PHP_EOL);
        }
        
      }
    }
    $result_s->close(); 

  }

}

fclose($fout);
$mysqli_s->close(); 
$mysqli_d->close(); 

function open_db($db)
{
  $mysqli = mysqli_init();
  if (!$mysqli) 
  {
      fwrite(STDOUT,'dest mysqli_init failed'.PHP_EOL);
      return;
  #    exit;
  }
   
  if (!$mysqli->options(MYSQLI_INIT_COMMAND, 'SET AUTOCOMMIT = 0')) 
  {
      fwrite(STDOUT,'Setting MYSQLI_INIT_COMMAND failed'.PHP_EOL);
      return;

  }
   
  if (!$mysqli->real_connect($db->host,$db->username,$db->pass,$db->db,$db->port)) 
  {
      fwrite(STDOUT,$db->host.' Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error.PHP_EOL);
      return;
  }

  if (!$mysqli->select_db($db->db))
  {
      fwrite(STDOUT,'Can not open db (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error.PHP_EOL);
      return;
  }
  return $mysqli;
}  


function field_string($filed)
{
  $s='';

  if ($filed[0])
  {
    for ($i=0; $i<count($filed);$i++)
    {
#      echo 'fld'.$i.'='.$filed[$i].PHP_EOL;
      switch ($i)
      {
      case 0;
      case 1; 
        $s.=$filed[$i].' '; 
        break;
      case 2: 
        if ($filed[$i]<>'YES') 
        {
          $s.='NOT NULL'.' '; 
          break;
        }
 //---------- will be add parsing structure field
      }
    }
  }
  return $s;
}

?>
