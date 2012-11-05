<?php   
 chdir('../');
 /*
     Example1 : A simple line chart
 */

 include_once("./config.php");
 include_once("./lib/loader.php");


 include_once(DIR_MODULES."application.class.php");

 $db=new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME); // connecting to database

 $settings=SQLSelect("SELECT NAME, VALUE FROM settings");
 $total=count($settings);
 for($i=0;$i<$total;$i++) {
  Define('SETTINGS_'.$settings[$i]['NAME'], $settings[$i]['VALUE']);
 }

// language selection by settings
if (SETTINGS_SITE_LANGUAGE && file_exists(ROOT . 'languages/' . SETTINGS_SITE_LANGUAGE . '.php')) include_once (ROOT . 'languages/' . SETTINGS_SITE_LANGUAGE . '.php');
include_once (ROOT . 'languages/default.php');

if (defined('SETTINGS_SITE_TIMEZONE')) {
 ini_set('date.timezone', SETTINGS_SITE_TIMEZONE);
}



 // Standard inclusions      
 include("./pChart/pData.class");   
 include("./pChart/pChart.class");   


  if (!$width) {
   $w=610;
  } else {
   $w=(int)$width;
  }

  
  // Dataset definition   
  $DataSet = new pData;

  if ($p!='') {
   if (preg_match('/(.+)\.(.+)/is', $p, $m)) {
    $obj=getObject($m[1]);
    $prop_id=$obj->getPropertyByName($m[2], $obj->class_id, $obj->id);
   }
  }

  //$type='';

  $pvalue=SQLSelectOne("SELECT * FROM pvalues WHERE PROPERTY_ID='".$prop_id."' AND OBJECT_ID='".$obj->id."'");

  if (!$pvalue['ID']) {
   exit;
  }

  if ($_GET['op']=='value') {
   echo $pvalue['VALUE'];exit;
  }

   $end_time=time();

   if ($_GET['px']) {
    $px_per_point=(int)$_GET['px'];
   } else {
    $px_per_point=6;
   }
   

 if (preg_match('/(\d+)d/', $type, $m)) {

   $total=(int)$m[1];
   $period=round(($total*24*60*60)/(($w-80)/$px_per_point)); // seconds
   $start_time=$end_time-$total*24*60*60;


 } elseif (preg_match('/(\d+)h/', $type, $m)) {

   $total=(int)$m[1];
   $period=round(($total*60*60)/(($w-80)/$px_per_point)); // seconds
   $start_time=$end_time-$total*60*60;

  } elseif (preg_match('/(\d+)m/', $type, $m)) {

   $total=(int)$m[1];
   $period=round(($total*31*24*60*60)/(($w-80)/$px_per_point)); // seconds
   $start_time=$end_time-$total*31*24*60*60;

  } elseif (preg_match('/(\d+)\/(\d+)\/(\d+)/', $_GET['start'], $m) && $_GET['interval']) {
   $period=(int)$_GET['interval']; //seconds
   $start_time=mktime(0, 0, 0, $m[2], $m[3], $m[1]);
   $total=1;
  }


  if ($total>0) {

   $px=0;
   $px_passed=0;

   $dt=date('Y-m-d', $start_time);

   while($start_time<$end_time) {

     $ph=SQLSelectOne("SELECT ID, VALUE FROM phistory WHERE VALUE_ID='".$pvalue['ID']."' AND ADDED<=('".date('Y-m-d H:i:s', $start_time)."') ORDER BY ADDED DESC LIMIT 1");
     if ($ph['ID']) {
      $values[]=(float)$ph['VALUE'];
     } else {
      $values[]=0;
     }

     if ($px_passed>30) {
      if (date('Y-m-d', $start_time)!=$dt) {
       $hours[]=date('d/m', $start_time);
       $dt=date('Y-m-d', $start_time);
      } else {
       $hours[]=date('H:i', $start_time);
      }
      $px_passed=0;
     } else {
      $hours[]='';
     }


     $start_time+=$period;
     $px+=$px_per_point;
     $px_passed+=$px_per_point;

   }

   $DataSet->AddPoint($values,"Serie1");  
   $DataSet->AddPoint($hours,"Serie3");  


  } else {

   $DataSet->AddPoint(0,"Serie1");
   $DataSet->AddPoint(0,"Serie3");
  
  }


  if ($_GET['op']=='values') {
   echo json_encode($values);
   exit;
  }


  if ($_GET['op']=='json') {
   //header("Content-type: text/json");
   $ret = array();
   $ret['VALUES']=$values;
   $ret['TIME']=$hours;
   echo json_encode($ret);
   exit;
  }


  $DataSet->AddAllSeries();  
  $DataSet->RemoveSerie("Serie3");  
  $DataSet->SetAbsciseLabelSerie("Serie3");  

  $DataSet->SetSerieName("24 hours","Serie1");  

  //$DataSet->SetYAxisName($p);  

  
  if ($unit) {
   $DataSet->SetYAxisUnit($unit);
  } else {
   $DataSet->SetYAxisUnit("�C");  
  }
  $DataSet->SetXAxisUnit("");  
   
  // Initialise the graph  


  if (!$height) {
   $h=210;
  } else {
   $h=(int)$height;
  }

  $Test = new pChart($w,$h);  

  $Test->setColorPalette(0,255,255,255);

  $Test->drawGraphAreaGradient(132,153,172,50,TARGET_BACKGROUND);  

  $Test->setFontProperties("./pChart/Fonts/tahoma.ttf",10);  
  if ($_GET['title']) {
   $Test->drawTitle(60,15,$_GET['title'],250,250,250);
  } else {
   $Test->drawTitle(60,15,$p,250,250,250);
  }


  $Test->setFontProperties("./pChart/Fonts/tahoma.ttf",8);  
  $Test->setGraphArea(60,20,$w-25,$h-30);  
  $Test->drawGraphArea(213,217,221,FALSE);  
  $Test->drawScale($DataSet->GetData(),$DataSet->GetDataDescription(),SCALE_NORMAL,213,217,221,TRUE,0,2);  
  $Test->drawGraphAreaGradient(162,183,202,50);  
  //$Test->drawGrid(4,TRUE,230,230,230,50); 

     
  // Draw the line chart  
  $Test->drawPlotGraph($DataSet->GetData(),$DataSet->GetDataDescription(),2);  

  if ($_GET['gtype']=='curve') {
   $Test->drawCubicCurve($DataSet->GetData(),$DataSet->GetDataDescription());
  } elseif ($_GET['gtype']=='bar') {
   $Test->drawBarGraph($DataSet->GetData(),$DataSet->GetDataDescription(),TRUE);
  } else {
   $Test->drawLineGraph($DataSet->GetData(),$DataSet->GetDataDescription());  
  }
  //
  

   
   
  // Render the picture  
  $Test->AddBorder(1); 

 Header("Content-type:image/png");
 imagepng($Test->Picture);
 //$Test->Render();


 $db->Disconnect(); // closing database connection