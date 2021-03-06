<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use DB;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class ObservationController extends BaseController
{

  public function createObservation(Request $request){

      // $observation = json_encode($_REQUEST['observation']);
      $decodedobservation =  $request->getContent();
      $decodedobservation = json_decode($decodedobservation);
      $id = $decodedobservation->id;      
      $subject = $decodedobservation->subject->reference;
      $effective = $decodedobservation->effectiveDateTime;
      $effective = date('Y-m-d H:i:s', strtotime($effective));
      $status = $decodedobservation->status;
      $error;
      $errorsystem;
      try{
      $error =$decodedobservation->dataAbsentReason->coding->code;
      $errorsystem =$decodedobservation->dataAbsentReason->coding->system;
      }catch(\Exception $ex){
          $errorsystem = "";
          $error = "";
      }
      
      try{
      $code = $decodedobservation->code->coding{0}->code;
      $value = $decodedobservation->valueQuantity->value;
      $valuesystem = $decodedobservation->valueQuantity->system;
      $valuecode =  $decodedobservation->valueQuantity->code;
      $valueunit = $decodedobservation->valueQuantity->unit;

      
      $addpatient_observation = \DB::SELECT("call sp_addpatient_observation(?,?,?,?,?,?,?,?,?,?,?)",[$id, $code, $value, $subject, $effective,$status,$error, $errorsystem,$valuesystem,$valuecode,$valueunit]);
      $addpatient_observation_report = json_encode(array('addpatient_observation_report' => $addpatient_observation ));
      $this->saveNotif($addpatient_observation, $subject, $code, $value); 
      echo $addpatient_observation_report;
      
      }catch(\Exception $ex){
          foreach($decodedobservation->component as $obsarray_content){

            if(isset($obsarray_content->valueQuantity->value)){
      $code = $obsarray_content->code->coding{0}->code;
      $value = $obsarray_content->valueQuantity->value;
      $valuesystem = $obsarray_content->valueQuantity->system;
      $valuecode =  $obsarray_content->valueQuantity->code;
      $valueunit = $obsarray_content->valueQuantity->unit;
      $addpatient_observation = \DB::SELECT("call sp_addpatient_observation(?,?,?,?,?,?,?,?,?,?,?)",[$id, $code, $value, $subject, $effective,$status,$error, $errorsystem,$valuesystem,$valuecode,$valueunit]);
      $addpatient_observation_report = json_encode(array('addpatient_observation_report' => $addpatient_observation ));
      $this->saveNotif($addpatient_observation, $subject, $code, $value); 
      echo $addpatient_observation_report;
    }else if(isset($obsarray_content->valueSampledData->data)){

     
      $code = $obsarray_content->code->coding{0}->code;
      $valuesystem = $obsarray_content->code->coding{0}->system;
      $originvalue = $obsarray_content->valueSampledData->origin->value;
      $period = $obsarray_content->valueSampledData->period;
      $factor = $obsarray_content->valueSampledData->factor;
      $dimensions = $obsarray_content->valueSampledData->dimensions;
      $data = $obsarray_content->valueSampledData->data;
        $client = new Client();
        $res = $client->request('POST', 'http://206.189.87.169:5000/analyze_ecg', [
            'form_params' => [
                'period' => $period,
                'factor' => $factor,
                'data' => $data,
            ]
        ]);
        $qt = $res->getBody();
      $addpatient_observation = \DB::SELECT("call sp_insertECG(?,?,?,?,?,?,?,?,?,?,?)",[$id, $status, $valuesystem, $subject, $effective,$originvalue,$period, $factor,$dimensions,$data,$qt]);
      $addpatient_observation_report = json_encode(array('addpatient_observation_report' => $addpatient_observation ));
      echo $addpatient_observation_report;


    }

          }



      }
    
  }

public function requestBP(){
  $patientid = trim($_POST['patientid']);
  $requestid = \DB::SELECT("call  sp_requestbp(?)",[$patientid]);  
  echo json_encode(array("RequestResult" => $requestid));
}

public function getOnDemandBP(){
  $getOnDemandBP = \DB::SELECT("call sp_getondemandbp()"); 
  $getOnDemandBP = json_encode(array('onDemandBP_report' => $getOnDemandBP ));
      echo $getOnDemandBP;
}

public function sendrequestBP(){
  $requestid = trim($_POST['requestid']);
  $bpvalue = trim($_POST['bpvalue']);
  \DB::SELECT("call  sp_postBP(?,?)",[$requestid,$bpvalue]);  
}

public function getRequestBPValue(){
  $requestid = trim($_POST['requestid']);
  $bpvalue = \DB::SELECT("call  sp_getBPValue(?)",[$requestid]);
  echo json_encode(array("BPValue" => $bpvalue));
}

public function saveNotif($addpatient_observation, $subject, $code, $value){
      
      //get patient upper and lower limit
      if(count($addpatient_observation) > 0) {
        foreach($addpatient_observation as $obsrow) { 
      $patientid = substr($subject, strpos($subject,"/")+1, strlen($subject));
      $patient_limits = \DB::SELECT("call sp_get_patient_config(?)",[$patientid]);
      if(count($patient_limits) > 0) {
        foreach($patient_limits as $row) { 
          switch ($code){
              case "8310-5":{ //Temperature
                if($value>$row->rpc_temperature_upper)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,2,"8310-5",$obsrow->id]);
                if($value<$row->rpc_temperature_lower)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,1,"8310-5",$obsrow->id]);
                break;
              }
              case "76282-3":{ //Heart Rate
                if($value>$row->rpc_heartrate_upper_bpm)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,2,"76282-3",$obsrow->id]);
                if($value<$row->rpc_heartrate_lower_bpm)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,1,"76282-3",$obsrow->id]);
                break;
              }
              case "8889-8":{ //Pulse Rate
                if($value>$row->rpc_pulserate_upper_bpm)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,2,"8889-8",$obsrow->id]);
                if($value<$row->rpc_pulserate_lower_bpm)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,1,"8889-8",$obsrow->id]);
                break;
              } 
              case "8480-6":{ //Systolic BP
                if($value>$row->rpc_bp_systolic_upper)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,2,"8480-6",$obsrow->id]);
                if($value<$row->rpc_bp_systolic_lower)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,1,"8480-6",$obsrow->id]);
                break;
              } 
              case "8462-4":{ //DIastolic BP
                if($value>$row->rpc_bp_diastolic_upper)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,2,"8462-4",$obsrow->id]);
                if($value<$row->rpc_bp_diastolic_lower)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,1,"8462-4",$obsrow->id]);
                break;
              }  
              case "59407-7":{ //SPO2 / Oxygen Saturation
                if($value>$row->rpc_oxygen_upper_saturation)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,2,"59407-7",$obsrow->id]);
                if($value<$row->rpc_oxygen_lower_saturation)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,1,"59407-7",$obsrow->id]);
                break;
              }
             case "76270-8":{ //Respiration Rate (secondary)
                if($value>$row->rpc_respiratory_upper_rpm)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,2,"76270-8",$obsrow->id]);
                if($value<$row->rpc_respiratory_upper_rpm)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,1,"76270-8",$obsrow->id]);
                break;
              }
             case "76171-8":{ //Respiration Rate (primary)
                if($value>$row->rpc_respiratory_upper_rpm)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,2,"76171-8",$obsrow->id]);
                if($value<$row->rpc_respiratory_upper_rpm)
                  \DB::SELECT("call sp_addnotif(?,?,?,?)",[$patientid,1,"76171-8",$obsrow->id]);
                break;
              }       
            } //end switch  
          }//end foreach patient_limits
        }// end patient_limit counts
      } //end patient_obs
    } //end patient_obs_count
}

 public function patientTimeFrame(){
  $patientid = trim($_GET['patient']);
 // try{
  $patientid = substr($patientid, strpos($patientid,"/")+1, strlen($patientid));
     $patient_timeframe = \DB::SELECT("call sp_getpatienttimeframe(?)",[$patientid]);
  $period = 0;
  if(count($patient_timeframe) > 0) {
            foreach($patient_timeframe as $row) { 
              $period = $row->rpc_time_frame;
            }
        }
  $getOnDemandBP = \DB::SELECT("call sp_getondemandbp()"); 
  $getOnDemandBP = json_encode(array('onDemandBP_report' => $getOnDemandBP ));

   $patientinfo = "Patient/".$patientid;
   $array = [
    'type' => "searchset",
    'total' => 1,
    'entry' =>[[
    'intent' => "order",
    'codeCodeableConcept'=> ['coding'=>[['code'=>"258057004", 'System'=>"http://snomed.info/sct"]]],
    'subject' => ['reference'=>$patientinfo],
    'occurenceTiming'=>array("repeat"=>array("frequency"=>1,"period"=>$period, "periodUnit"=>"m"))
    ]]   
];
$myObj = new \stdClass();
$myObj->dr = $array;
$myObj->bp = \DB::SELECT("call sp_getondemandbp()"); 

echo json_encode($myObj);

//echo json_encode($array);
//echo $getOnDemandBP;

//}catch(\Exception $ex){
  //$array = [
    //'type' => "searchset",
   // 'total' => 0,
    //'entry' => []
  //];
  //echo json_encode($array);
//}

    }

    public function getPatientRangedObservation(){

      try{
    $obscode = $_POST['obscode'];
    $spec_date = $_POST['spec_date'];
    $patientid = $_POST['patientid'];
    $utc_offset = $_POST['utc_offset'];
    $patientid = "Patient/".$patientid;
    $PatientRangedObservation = \DB::SELECT("call  sp_getPatientObservationRange(?,?,?,?)",[$obscode,$spec_date,$patientid,$utc_offset]);
      $PatientRangedObservation = json_encode(array('PatientRangedObservation' => $PatientRangedObservation ));
      echo $PatientRangedObservation;

      }catch(\Exception $ex){
          echo "invalid request";
      }

    }


   public function create_statuscode(Request $request){

    $name = $_POST['name'];
    $descr = $_POST['descr'];
    $category = $_POST['category'];
    $create_statuscode = \DB::SELECT("call  sp_createstatuscode(?,?,?)",[$name,$descr,$category]);
      $create_statuscode_report = json_encode(array('create_statuscode' => $create_statuscode ));
      echo $create_statuscode_report;

 }

 public function delete_statuscode(Request $request){

    $codeid = $_POST['codeid'];
    $delete_statuscode = \DB::SELECT("call  sp_deletestatuscode(?)",[$codeid]);
      $delete_statuscode_report = json_encode(array('delete_statuscode' => $delete_statuscode ));
      echo $delete_statuscode_report;

 }

  public function filter_statuscode(){

    $statuscode = $_GET['statuscode'];
    $filter_statuscode = \DB::SELECT("call  sp_filterstatuscode(?)",[$statuscode]);
      $filter_statuscode = json_encode( array('filter_statuscode_report'=>$filter_statuscode) );
      echo $filter_statuscode;

 }

  public function get_statuscode(Request $request){

    $get_statuscode = \DB::SELECT("call sp_getstatuscode()");
      $get_statuscode_report = json_encode(array('statuscode_report' => $get_statuscode ));
      echo $get_statuscode_report;

 }

  	
}
