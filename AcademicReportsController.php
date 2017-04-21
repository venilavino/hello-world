<?php

use \Phalcon\Tag as Tag,
    \Phalcon\Mvc\Model\Criteria,
    Phalcon\Http\Request\File,
    Phalcon\Mvc\View;

class AcademicReportsController extends ControllerBase {

    protected function initialize() {
        $this->tag->setTitle("Edu");
        $this->view->setTemplateAfter('private');
    }

    public function indexAction() {
        $this->tag->prependTitle("Academic Reports| ");
        $this->view->id = $this->request->get('id') ? $this->request->get('id') : '';
    }

    public function loadClassRatingReportAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
    }

    public function loadSubtreeRatingAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost() && $this->request->getPost('orgvalueid') != '') {
            $this->view->orgvalueid = $orgvalueid = $this->request->getPost('orgvalueid');
            $query_param = array(
                'columns' => 'org_master_id',
                'conditions' => 'parent_id = ' . $orgvalueid,
                'group' => 'org_master_id',
            );
            $this->view->org_value = OrganizationalStructureValues::find($query_param);
        }
    }

    public function loadSubtreeforattAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost() && $this->request->getPost('orgvalueid') != '' && $this->request->getPost('action') != '') {
            $this->view->orgvalueid = $orgvalueid = $this->request->getPost('orgvalueid');
            $query_param = array(
                'columns' => 'org_master_id',
                'conditions' => 'parent_id = ' . $orgvalueid,
                'group' => 'org_master_id',
            );
            $this->view->org_value = OrganizationalStructureValues::find($query_param);
            $this->view->action = $this->request->getPost('action');
        }
    }

    public function loadRatingsListAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();

        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {

                $params[$key] = $value;
            }
        }
        $res = $this->organizationalStructure->buildExamQuery(implode(',', $params['aggregateids']));
        $this->view->rating_det = $rating_det = RatingDivision::find(implode(' or ', $res));
        foreach ($rating_det as $nodes) {
            $rating_name[] = $nodes->rating_name;
        }
        $this->view->ratingname = $ratingname = $rating_name;
    }

    public function loadSubtreesAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost() && $this->request->getPost('orgvalueid') != '') {
            $this->view->orgvalueid = $orgvalueid = $this->request->getPost('orgvalueid');
            $this->view->action = $action = $this->request->getPost('action');
            $this->view->org_value = $org_value = OrganizationalStructureValues::find('parent_id = ' . $orgvalueid
                            . ' GROUP BY  org_master_id ');

//             $this->view->org_mas_value = $org_mas_value = OrganizationalStructureMaster::find('parent_id = '.$orgvalueid);
        }
    }

    public function loadStudentsClassRatingAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();

        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        if (isset($params['rating_name'])) {
            $rating_name = RatingDivision::findFirstById($params['rating_name'])->rating_name;
            $classroomname = $this->organizationalStructure->getNameForKeys(implode(',', $params['aggregateids']));
            $this->view->classroomname = $fff = str_replace(' >>', implode(', ', $classroomname));
            $resarr = $this->getClsRatArr($params);
            $this->view->rowArr = $resarr[1];
            $this->view->yaxisuniqueArr = $resarr[0];
            $this->view->reportHeader = 'Rating Report :' . implode(' ', $classroomname) . '>>' . $rating_name;
        }
    }

    public function getClsRatArr($params) {
        $subjpids = $this->organizationalStructure->getAlSubjChildNodes($params['aggregateids']);
        $subjids = $this->organizationalStructure->getGrpSubjMasPossiblities($params['aggregateids'], 'Class');
        $res = $this->organizationalStructure->buildStudentQuery(implode(',', $params['aggregateids']));
        $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                . 'stumap.status'
                . ' FROM StudentHistory stumap LEFT JOIN'
                . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE   '
                . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
        $students = $this->modelsManager->executeQuery($stuquery);

        $rowArr = $yaxis = array();
        foreach ($students as $stu) {
            $ratingCategorys = RatingCategoryMaster::find();
            foreach ($ratingCategorys as $ratingCategory) {
                $ratingPoints = 0;
                $ratingValues = RatingCategoryValues::find('rating_category = ' . $ratingCategory->id);
                $ratTotPoints = $ratingCategory->category_weightage;
                foreach ($ratingValues as $rvalue) {
                    $clststQury = array();
                    if (count($subjids) > 0)
                        $clststQury[] = ("  class_master_id IN (" . implode(' , ', $subjids) . ")");
                    $clststQury[] = 'student_id = ' . $stu->student_info_id;
                    $clststQury[] = '  rating_division_id =' . $params['rating_name'];
                    $clststQury[] = '  rating_category =' . $ratingCategory->id;
                    $clststQury[] = '  rating_value = ' . $rvalue->id;
                    $studentRating = StudentClassteacherRating::findFirst(implode(' and ', $clststQury));
                    if ($studentRating && $studentRating->rating_id > 0) {
                        $ratingPoints += ($rvalue->rating_level_value / 100) * $ratingCategory->category_weightage;
                    }
                }
                $rowArr[$stu->student_info_id][$ratingCategory->id] = array(
                    'rating_name' => $ratingCategory->category_name,
                    'ratingPoints' => $ratingPoints,
                    'StudentName' => $stu->Student_Name,
                    'StudentID' => $stu->student_info_id,
                    'aggreegatekey' => $stu->aggregate_key
                );

                $yaxis[$ratingCategory->id] = $ratingCategory->category_name . " ($ratTotPoints pts)";
                $yaxis[$ratingCategory->id] = array(
                    'categoryname' => $ratingCategory->category_name,
                    'ratPoints' => $ratTotPoints);
            }
        }
//echo '<pre>';print_r($rowArr);exit;
        return array($yaxis, $rowArr);
    }

    public function getRatingReportAction() {

        $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
        $params = $queryParams = array();
        $aggregateval = '';
        $ratingdata = json_decode($this->request->getPost('params'));
        foreach ($ratingdata as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {

                $params[$key] = $value;
            }
        }

        $resarr = $this->getClsRatArr($params);
        $this->view->rowArr = $rowArr = $resarr[1];
        $this->view->yaxisuniqueArr = $yaxis = $resarr[0];
        $acdyrMas = OrganizationalStructureMaster::findFirst('mandatory_for_admission = 1 and cycle_node = 1');
        $this->mandnode = $this->_getMandNodesForAssigning($acdyrMas);
        $header[] = 'Student Name';
        $clsscnt = 0;
        foreach ($this->mandnode as $node) {
            if ($clsscnt != 0) {
                $header[] = ucfirst($node);
            }
            $clsscnt++;
        }
        foreach ($yaxis as $yaxisval) {
            $header[] = $yaxisval['categoryname'];
        }
        $icnt = 0;
        $reportdata = array();
        foreach ($rowArr as $stuarr) {
            $reportval = array();
            $stu_cnt = 0;
            foreach ($stuarr as $stuarrval) {
                if ($stu_cnt == 0) {
                    $reportval[] = $stuarrval['StudentName'];
                    $aggregatevals = $stuarrval['aggreegatekey'] ? explode(',', $stuarrval['aggreegatekey']) : '';
                    $class_arr = array();
                    $aggcnt = 0;
                    if ($aggregatevals != '') {

                        foreach ($aggregatevals as $aggregateval) {
                            if ($aggcnt != 0) {
                                $orgnztn_str_det = OrganizationalStructureValues::findFirstById($aggregateval);
                                $orgnztn_str_mas_det = OrganizationalStructureMaster::findFirstById($orgnztn_str_det->org_master_id);
                                $class_arr[$orgnztn_str_mas_det->id] = $orgnztn_str_det->name ? $orgnztn_str_det->name : '-';
                            }
                            $aggcnt++;
                        }
                        $aggcntval = 0;
                        foreach ($this->mandnode as $key => $mandnodeval) {
                            if ($aggcntval != 0) {
                                $reportval[] = isset($class_arr[$key]) ? $class_arr[$key] : '-';
                            }
                            $aggcntval++;
                        }
                    }
                }

                $reportval[] = $stuarrval['ratingPoints'];
                $stu_cnt++;
            }
            $reportdata[] = $reportval;
        }
        $filename = 'Student_Class_Rating_List_' . date('d-m-Y') . '.csv';
        $param['filename'] = $filename;
        $param['header'] = $header;
        $param['data'] = $reportdata;
        $this->generateXcel($param);
    }

    public function generateXcel($param) {
        $filename = $param['filename'];
        $header = $param['header'];
        $reportdata = $param['data'];

        $unlink_file = DOWNLOAD_DIR . $filename;
        if (file_exists($unlink_file)) {
            unlink($unlink_file);
        }
        $fp = fopen(DOWNLOAD_DIR . $filename, 'a');
        $delimiter = ",";
        fputcsv($fp, $header, $delimiter);
        foreach ($reportdata as $reportsval) {
            fputcsv($fp, $reportsval, $delimiter);
        }
        fclose($fp);
        $file = DOWNLOAD_DIR . $filename;
        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '";');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            ob_clean();
            flush();
            readfile($file);
            exit;
        }
    }

    public function _getNonMandNodesForAssigning($id, $nodes = array()) {
        $fields = OrganizationalStructureMaster::find('parent_id=' . $id);
        if (count($fields) > 0):
            foreach ($fields as $field) {
// if ($field->mandatory_for_admission != 1) {
                if ($field->is_subordinate != 1) {
                    $nodes[$field->id] = $field->name;
// } else {
                    $nodes = $this->_getNonMandNodesForAssigning($field->id, $nodes);
                }
            }
        endif;
        return $nodes;
    }

    public function _getMandNodesForAssigning($acdyrMas, $nodes = array()) {

        $nodes[$acdyrMas->id] = $acdyrMas->name;
//  echo $acdyrMas->name;
        $fields = OrganizationalStructureMaster::find('parent_id=' . $acdyrMas->id);
        if (count($fields) > 0):
            foreach ($fields as $field) {
//if ($field->mandatory_for_admission == 1) {
                if ($field->is_subordinate != 1) {
//                    $nodes[$field->id] = $field->name;
                    $nodes = $this->_getMandNodesForAssigning($field, $nodes);
                }
            }
        endif;
        return $nodes;
    }

    public function _getMandNodesForSubject($acdyrMas, $nodes = array()) {
//        echo 'test';exit;
        $fields = OrganizationalStructureValues::findFirstById($acdyrMas->parent_id);
        $query = 'id =' . $acdyrMas->org_master_id . ' AND module ="Subject"';
        $org_mas = OrganizationalStructureMaster::find(array(
                    $query,
                    "columns" => "COUNT(*) as cnt"
        ));
        if (isset($org_mas[0]->cnt) && $org_mas[0]->cnt == 1):
            $nodes[$acdyrMas->id] = $acdyrMas->name;
//                echo $acdyrMas->name;
            if (isset($fields->parent_id)):
//            echo $fields->parent_id;
                $nodes = $this->_getMandNodesForSubject($fields, $nodes);
            endif;
        endif;

        return $nodes;
    }

    public function loadAttendanceReportAction() {
        $this->tag->prependTitle("Attendance Report | ");
    }

    public function loadSubtreeforAttendanceAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost() && $this->request->getPost('orgvalueid') != '') {
            $this->view->orgvalueid = $orgvalueid = $this->request->getPost('orgvalueid');
            $query_param = array(
                'columns' => 'org_master_id',
                'conditions' => 'parent_id = ' . $orgvalueid,
                'group' => 'org_master_id',
            );
            $this->view->org_value = OrganizationalStructureValues::find($query_param);
            $this->view->action = $this->request->getPost('action');
        }
    }

    public function viewAttendanceAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost()) {
            $params = $queryParams = array();
            foreach ($this->request->getPost() as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv[0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else {
                    $params[$key] = $value;
                }
            }

            $classroomname = $this->organizationalStructure->getNameForKeys(implode(',', $params['aggregateids']));
            $this->view->classroomname = str_replace('>>', ' ', implode(', ', $classroomname));
//         print_r( $this->view->classroomname);exit;
            $this->view->aggregateids = $gvnparams['aggregateids'] = implode(',', $params['aggregateids']);
            $this->view->reptyp = $gvnparams['reptyp'] = $params['reptyp'];

            $calres = $this->calculateAttendancePercentMonthly($gvnparams);
            if (count($calres) > 0) {
                $this->view->classStudents = $calres[0];
                $this->view->monthlyPercent = $calres[1];
                $this->view->monthhead = $calres[4];
                $this->view->valhead = $calres[3];
                $this->view->legend = $calres[2];
                $this->view->result = $calres[5];
                $this->view->st = $calres[6];
                $this->view->et = $calres[7];
            } else {

                $this->view->error = '<div class="alert alert-danger">Attendance Not Yet Taken</div>';
            }
        }
    }

    public function calculateAttendancePercentMonthly($gvnparams) {
        $aggregateids = $gvnparams['aggregateids'];
        $reptyp = $gvnparams['reptyp'];
        $newarr = array();
        $user_type = 'student';
        $res = $this->organizationalStructure->buildStudentQuery($aggregateids);
        $stuquery = 'SELECT stumap.id,stumap.student_info_id,stumap.aggregate_key,'
                . 'stumap.subordinate_key,stumap.status,stuinfo.loginid,stuinfo.Date_of_Joining,'
                . ' stuinfo.Admission_no,stuinfo.photo,stuinfo.Student_Name '
                . ' FROM StudentMapping stumap LEFT JOIN'
                . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass"'
                . '  and (' . implode(' or ', $res)
                . ') ORDER BY stuinfo.Student_Name ASC';
        $classStudents = $this->modelsManager->executeQuery($stuquery);
        $monthlyPercent = $result = array();
        if (count($classStudents) > 0):
            $params['monthhead'] = array();
            $params['valhead'] = array();
            foreach ($classStudents as $classStudent) {
                $overallPercent = array();
                $params['user_id'] = $classStudent->loginid;
                $params['student_id'] = $classStudent->student_info_id;
                $params['doj'] = $classStudent->Date_of_Joining;
                $params['user_type'] = 'student';
                $overallPercent = AcademicReportsController::getAttendancePercentMonthly($params);
                $monthlyPercent = array_merge($monthlyPercent, $overallPercent[0]);
                $params['monthhead'] = ($overallPercent[1]);
                $params['valhead'] = ($overallPercent[2]);
                $params['legend'] = ($overallPercent[3]);
            }
        endif;
        $i = $j = 0;
        $mntharr = array_filter(array_unique($params['monthhead']));
        usort($mntharr, 'AcademicReportsController::month_compare');
        if (count($mntharr) > 0) {
            $firstele = (count($mntharr) > 1) ? array_shift($mntharr) : $mntharr[0];
            $lastele = (count($mntharr) > 1) ? array_pop($mntharr) : $mntharr[0];
            $startdt = (date('Y-m-01 00:00:00', strtotime('01-' . $firstele)));
            $enddt = (date('Y-m-t 00:00:00', strtotime('01-' . $lastele)));
            $begin = new DateTime($startdt);
            $end = new DateTime($enddt);
            $interval = DateInterval::createFromDateString('1 month');
            $period = new DatePeriod($begin, $interval, $end);
            foreach ($period as $dt) {
                $monthhead[] = ($dt->format("m-Y"));
            }

            $valhead = array_unique($params['valhead']);
            if ($reptyp == 1) {

                $legend = array();

                $result['thead'][$i][$j]['text'] = 'Students';
                $result['thead'][$i][$j]['rowspan'] = '';
                $result['thead'][$i][$j]['colspan'] = '';
                foreach ($classStudents as $classStudent) {
                    if (count($monthhead) > 0) {
                        foreach ($monthhead as $hvalue) {
                            $atttaken = $stu_att_totByVal = 0;
                            if (count($valhead) > 0) {
                                foreach ($valhead as $vvalue) {
                                    $student_att_props_val = AttendanceSelectbox::findFirst("attendance_for = '$user_type'"
                                                    . ' and attendanceid= "' . $vvalue . '"');
                                    $noofperiods = PeriodMaster::find('LOCATE(node_id,"' . str_replace(',', '-', $classStudent->aggregate_key) . '" )' . " and user_type = 'student'");
                                    $counttaken = $monthlyPercent[$classStudent->loginid][$hvalue][$vvalue] ? ($monthlyPercent[$classStudent->loginid][$hvalue][$vvalue] / $noofperiods) : 0;
                                    $atttaken += ($counttaken / count($noofperiods));
                                    $stu_att_totByVal += ($counttaken / count($noofperiods)) * $student_att_props_val->attendancevalue;
                                }
                            }
                            $monthlyPercent[$classStudent->loginid][$hvalue]['percent'] = $atttaken > 0 ? (round($stu_att_totByVal / $atttaken * 100, 2)) : '';
                        }
                    }
                }

                if (count($monthhead) > 0) {
                    foreach ($monthhead as $hvalue) {
                        $j++;
                        $monthhdr = explode('-', $hvalue);
                        $result['thead'][$i][$j]['text'] = '<a href="javascript:void(0)" onclick="academicSettings.loadDailyAttnReport(this)"
                       class="monthExpand" month="' . $monthhdr['0'] . '" year="' . $monthhdr['1'] . '" 
                           aggregateids="' . $aggregateids . '" reptyp="1"  >
                        <span class="btn btn-round btn-info">' . date('M - Y', strtotime('01-' . $hvalue)) . '</span></a>';
                        $result['thead'][$i][$j]['rowspan'] = '';
                        $result['thead'][$i][$j]['colspan'] = '';
                    }

                    $j++;
                    $result['thead'][$i][$j]['text'] = 'Total';
                    $result['thead'][$i][$j]['rowspan'] = '';
                    $result['thead'][$i][$j]['colspan'] = '';
                }
            } else {

                foreach ($classStudents as $classStudent) {
                    $noofperiods = PeriodMaster::find('LOCATE(node_id,"' . str_replace(',', '-', $classStudent->aggregate_key) . '" )' . " and user_type = 'student'");
                    if (count($monthhead) > 0) {
                        foreach ($monthhead as $hvalue) {
                            if (count($valhead) > 0) {
                                foreach ($valhead as $vvalue) {
                                    $atttaken = $stu_att_totByVal = 0;
                                    $counttaken = $monthlyPercent[$classStudent->loginid][$hvalue][$vvalue] ? ($monthlyPercent[$classStudent->loginid][$hvalue][$vvalue] ) : 0;
                                    $atttaken += ($counttaken / count($noofperiods));
                                    $monthlyPercent[$classStudent->loginid][$hvalue][$vvalue] = ($atttaken > 0) ? $atttaken : '';
                                }
                            }
                        }
                    }
                }
                $legend = array_unique($params['legend']);
                $result['thead'][$i][$j]['text'] = 'Students';
                $result['thead'][$i][$j]['rowspan'] = '2';
                $result['thead'][$i][$j]['colspan'] = '';

                if (count($monthhead) > 0) {
                    foreach ($monthhead as $hvalue) {
                        $j++;
                        $monthhdr = explode('-', $hvalue);
                        $result['thead'][$i][$j]['text'] = '<a href="javascript:void(0)" onclick="academicSettings.loadDailyAttnReport(this)"
                       class="monthExpand" month="' . $monthhdr['0'] . '" year="' . $monthhdr['1'] . '" 
                           aggregateids="' . $aggregateids . '" reptyp="0"  >
                        <span class="btn btn-round btn-info">' . date('M - Y', strtotime('01-' . $hvalue)) . '</span></a>';
                        $result['thead'][$i][$j]['rowspan'] = '';
                        $result['thead'][$i][$j]['colspan'] = count($valhead);
                    }
                    $j++;
                    $result['thead'][$i][$j]['text'] = 'Total';
                    $result['thead'][$i][$j]['rowspan'] = '';
                    $result['thead'][$i][$j]['colspan'] = count($valhead);
                }
                $i++;
                if (count($monthhead) > 0) {
                    foreach ($monthhead as $hvalue) {
                        if (count($valhead) > 0) {
                            foreach ($valhead as $vvalue) {
                                $j++;
                                $result['thead'][$i][$j]['text'] = $vvalue;
                                $result['thead'][$i][$j]['rowspan'] = '';
                                $result['thead'][$i][$j]['colspan'] = '';
                            }
                        }
                    }
                    if (count($valhead) > 0) {
                        foreach ($valhead as $vvalue) {
                            $j++;
                            $result['thead'][$i][$j]['text'] = $vvalue;
                            $result['thead'][$i][$j]['rowspan'] = '';
                            $result['thead'][$i][$j]['colspan'] = '';
                        }
                    }
                }
            }
            $newarr = array($classStudents, $monthlyPercent, $legend, $valhead, $monthhead, $result, $firstele, $lastele, '');
        }
        return $newarr;
    }

    public function getAttendancePercentMonthly($params) {
        $user_id = $params['user_id'];
        $user_type = $params['user_type']; //staff or student
        $params['type'] = 'attendance';
        $per = $attvalueDays = array();
        $monthhead = $params['monthhead'];
        $valhead = $params['valhead'];
        $legend = $params['legend'];

        $obj = new Cassandra();
        $res = $obj->connect(CASSANDRASERVERIP, '', '', SUBDOMAIN, CASSANDRASERVERPORT);
        if ($res) {
            $buildquery = "select * from month_attendance where"
                    . " user_id = '" . $user_id . "'";
            if ($result = $obj->query($buildquery)) {
                for ($i = 0; $i < count($result); $i++):
                    $abbrevation = AttendanceSelectbox::findFirstById($result[$i]['value']);
                    $monthhead[] = $result[$i]['month'];
                    $valhead[$abbrevation->id] = $abbrevation->attendanceid;
                    $legend[$abbrevation->id] = $abbrevation->attendanceid . ' - ' . $abbrevation->attendancename;
                    $attvalueDays[$user_id][$result[$i]['month']][$abbrevation->attendanceid] = $result[$i]['counter_value'];
                endfor;
            }
        }
        $obj->close();
        ksort($legend);
        ksort($valhead);
        return array($attvalueDays, $monthhead, $valhead, $legend);
    }

    public function month_compare($a, $b) {
        $t1 = strtotime('01-' . $a);
        $t2 = strtotime('01-' . $b);
        return $t1 - $t2;
    }

    public function loadDailyReportAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost()) {
            $params = $queryParams = array();
            foreach ($this->request->getPost() as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv[0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else {

                    $params[$key] = $value;
                }
            }
            $res = ControllerBase::buildStudentQuery(implode(',', $params['aggregateids']));
            $stuquery = 'SELECT stumap.id,stumap.student_info_id,stumap.aggregate_key,'
                    . 'stumap.subordinate_key,stumap.status'
                    . ' FROM StudentMapping stumap LEFT JOIN'
                    . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass"'
                    . '  and (' . implode(' or ', $res)
                    . ') ORDER BY stuinfo.Student_Name ASC';
            $classStudents = $this->modelsManager->executeQuery($stuquery);
            $dailyPercent = array();
            if (count($classStudents) > 0):
                foreach ($classStudents as $classStudent) {
                    $overallPercent = array();
                    $params['user_id'] = $classStudent->student_info_id;
                    $params['user_type'] = 'student';
                    $params['month'] = $params['month'];
                    $params['year'] = $params['year'];
                    $monthPercent = AttendanceController::getAttendancePercentDaily($params);
                    $dailyPercent[] = $monthPercent;
                }
            endif;
            $this->view->daillyPer = $dailyPercent;
        }
    }

    public function mainExamReportAction() {
        $this->tag->prependTitle("Exam Report | ");
    }

    public function loadSubtreeAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost() && $this->request->getPost('orgvalueid') != '') {
            $this->view->orgvalueid = $orgvalueid = $this->request->getPost('orgvalueid');
            $query_param = array(
                'columns' => 'org_master_id',
                'conditions' => 'parent_id = ' . $orgvalueid,
                'group' => 'org_master_id',
            );
            $this->view->org_value = OrganizationalStructureValues::find($query_param);
        }
    }

    public function loadExamListAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();
        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {

                $params[$key] = $value;
            }
        }
        $res = $this->organizationalStructure->buildExamQuery(implode(',', $params['aggregateids']));
        $mainexamdet = Mainexam::find(implode(' or ', $res));
        $this->view->examdet = $mainexamdet;
    }

    public function mainExamReportDetAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();

        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        $this->view->mainExId = $mainExam_id = $params['mainexam'];
        if ($mainExam_id) {
            $classroomname = $this->organizationalStructure->getNameForKeys(implode(',', $params['aggregateids']));
            $this->view->classroomname = str_replace('>>', ' ', implode(', ', $classroomname));
            $subjpids = $this->organizationalStructure->getAlSubjChildNodes($params['aggregateids']);
            $subjids = $this->organizationalStructure->getGrpSubjMasPossiblitiesold($params['aggregateids']);
//        $subjects = count($subjids)>0?ControllerBase::getAllPossibleSubjects($subjids):'';
            // $subjids = ControllerBase::getGrpSubjMasPossiblitiesold($params['aggregateids']);
            $subjects = $this->organizationalStructure->getAllPossibleSubjectsold($subjpids);
//print_r($subjids);exit;
            $subj_Ids = array();
            if (count($subjects) > 0) {
                foreach ($subjects as $nodes) {
                    $subj_Ids[] = $nodes->id;
                }
            }
            $this->view->subject_id = $subj_Ids;
            $res = $this->organizationalStructure->buildStudentQuery(implode(',', $params['aggregateids']));
            $stuquery = 'SELECT stuhis.id,stuhis.student_info_id,stuhis.aggregate_key,stuhis.status'
                    . ' FROM StudentHistory stuhis LEFT JOIN'
                    . ' StudentInfo stuinfo ON stuinfo.id=stuhis.student_info_id WHERE '
                    . '(' . implode(' and ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
            $this->view->students = $students = $this->modelsManager->executeQuery($stuquery);
            $this->view->stumapdet = $students;
            $this->view->sub_mas_id = implode(',', $subjids);
            $this->view->aggregateids = $params['aggregateids'];
            $this->view->pdfHead = 'Exam Report :' . implode(' - ', $classroomname);
        }
    }

    public function sendMessageToStudentsAction() {
        $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
        $params = $queryParams = array();

        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        $subjpids = ControllerBase::getAlSubjChildNodes($params['aggregateids']);
        $subjids = ControllerBase::getGrpSubjMasPossiblitiesold($params['aggregateids']);
        $subjects = ControllerBase::getAllPossibleSubjectsold($subjpids);

        $subj_Ids = array();
        foreach ($subjects as $nodes) {
            $subj_Ids[] = $nodes->id;
        }
        $this->view->subject_id = $subj_Ids;

        $res1 = ControllerBase::buildStudentQuery(implode(',', $params['aggregateids']));
        $stuquery = 'SELECT stumap.id,stumap.student_info_id,stumap.aggregate_key,'
                . 'stumap.status, stuinfo.Phone, stuinfo.f_phone_no_status,
                    stuinfo.f_phone_no_status,stuinfo.f_phone_no,
                        stuinfo.m_phone_no_status,stuinfo.m_phone_no,
                        stuinfo.g_phone_no_status,stuinfo.g_phone,stuinfo.Student_Name '
                . ' FROM StudentHistory stumap LEFT JOIN'
                . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                . '(' . implode(' and ', $res1) . ') ORDER BY stuinfo.Student_Name ASC';

        $stumapdet = $this->modelsManager->executeQuery($stuquery);
        $mainExId = $params['mainexam'];
        $sub_mas_id = implode(',', $subjids);

        $exist = OrganizationalStructureValues::find(array('columns' => 'group_concat(parent_id) as pids',
                    ' id IN ( ' . implode(',', $subj_Ids) . ')'));
        $res = MarkController::get_rowvalinputforreport($params['mainexam'], explode(',', $exist[0]->pids));
        $mainexamdet = Mainexam::findFirstById($mainExId);
        $failed_sms = 0;
        $no_of_msgs = 0;
        $total_msg = 0;
        $header = array('Name', 'Mobile No', 'Message', 'Date', 'Status', 'Smsid');
        $dateval = date('dmYHis');
        $filename = 'MAIN_EXAM_REPORT_' . $dateval . '.csv';

        $fp = fopen(DOWNLOAD_DIR . $filename, 'a');
        $delimiter = ",";
        fputcsv($fp, $header, $delimiter);
        $template = 'Your child ##Student Name##  main exam marks in ##Mainexam## ##Marks##';
        $check_arr = array();
        if (count($stumapdet) > 0) {
            foreach ($stumapdet as $stu) {
                $check_arr_data = $stu_data = array();
                $check_arr_msg = '';
                $examdet = ExamReportController::find_childtreevalinputforsms($res[1], array($stu->student_info_id, $mainExId, $sub_mas_id));
                $examname = $mainexamdet->exam_name;
                $check_arr_data['Name'] = $stu->Student_Name;
                $phoneno = ($stu->f_phone_no_status == 1) ? $stu->f_phone_no :
                        ( ($stu->m_phone_no_status == 1) ? $stu->m_phone_no :
                                (($stu->g_phone_no_status == 1) ? $stu->g_phone : $stu->Phone));

                $check_arr_data['phone'] = $phoneno; //'9843258019'; // 
                $aggregatevals = $stu->aggregate_key ? explode(',', $stu->aggregate_key) : '';
                $class = array();
                if ($aggregatevals != '') {
                    foreach ($aggregatevals as $aggregateval) {
                        $orgnztn_str_det = OrganizationalStructureValues::findFirstById($aggregateval);
                        $orgnztn_str_mas_det = OrganizationalStructureMaster::findFirstById($orgnztn_str_det->org_master_id);
                        $class[$orgnztn_str_mas_det->id] = $orgnztn_str_det->name ? $orgnztn_str_det->name : '-';
                    }
                }
                array_shift($class);
                $check_arr_data['class'] = implode('-', $class);
                $check_arr_msg['smstxt'] = "Your child " . $check_arr_data['Name']
                        . " Main Exam marks in "
                        . $examname . " 
";
                $check_arr_msg['smstxt'] .= implode(' <br> ', $examdet);
                $template = "Your child " . $check_arr_data['Name']
                        . " Main Exam marks in "
                        . $examname . " 
";
                $template .= implode(' <br>', $examdet);
                $check_arr[] = $check_arr_data;
                $template = preg_replace("/[\r\n]+/", "\n", $template);
                $template = preg_replace("/\s+/", ' ', $template);
                $stu_data[] = $stu->Student_Name;
                $stu_data[] = $check_arr_data['phone'];
                $stu_data[] = $template;
                $stu_data[] = date('d-m-Y');

//                $return = $this->sendSMSByGateway($check_arr_data['phone'], $check_arr_msg['smstxt']);

                $return = ControllerBase::sendSMSByGateway($check_arr_data['phone'], $check_arr_msg['smstxt']);
                $sentsmsstatus = new SentSmsResults();
                $sentsmsstatus->user_id = $stu->student_info_id;
                $sentsmsstatus->message = $template;
                $sentsmsstatus->date = time();
                if ($return == 0) {
                    $stu_data[] = 'Failed';
                    $sentsmsstatus->message_status = 'Failed';
                    $failed_sms++;
                } else {
                    $stu_data[] = 'Success';
                    $sentsmsstatus->message_status = 'Success';
                    //divide the message length by 160, 1 msg = 160 characters
                    $total_msg = round(strlen($check_arr_msg['smstxt']) / 160);
                    $no_of_msgs = $no_of_msgs + $total_msg;
                }
                if (preg_match('/^[0-9]*$/', $return)) {
                    $stu_data[] = $return;
                    $sentsmsstatus->responseid = $return;
                } else {
                    $stu_data[] = '';
                    $sentsmsstatus->responseid = '';
                }

                $fp = fopen(DOWNLOAD_DIR . $filename, 'a');
                fputcsv($fp, $stu_data, $delimiter);
            }
            fclose($fp);
        }
        $newtemplate = SmsTemplates::findFirst('temp_content = "' . $template . '"') ?
                SmsTemplates::findFirst('temp_content = "' . $template . '"') :
                new SmsTemplates();
        $newtemplate->temp_content = $template;
        $newtemplate->temp_status = 'Approved';
        $newtemplate->tags = 'template';
        $newtemplate->is_active = 1;
        $newtemplate->save();

        $sentsms = new SentSms();
        $sentsms->template_id = $newtemplate->temp_id;
        $sentsms->time = time();
        $sentsms->number_of_recipients = count($stumapdet);
        $sentsms->number_of_failures = $failed_sms;
        $sentsms->number_of_messages = $no_of_msgs;
        $sentsms->report_file = $filename;
        $sentsms->save();

        if ($no_of_msgs > 0) {
            $message['type'] = 'success';
            $message['message'] = '<div class="alert alert-block alert-success fade in">Message sent successfully!</div>'; // $appln->status . ' Successfully!';
            print_r(json_encode($message));
            exit;
        } else {
            $message['type'] = 'success';
            $message['message'] = '<div class="alert alert-block alert-success fade in">Message sending failed..!</div>'; // $appln->status . ' Successfully!';
            print_r(json_encode($message));
            exit;
        }
    }

    public function sendSMSByGateway($number, $message) {

        $smsprovider = CountrySmsprovider::findFirst('domain = "' . SUBDOMAIN . '"');
//        if ($provider == $smsprovider->) { //country INDIA

        $error_description = array('101' => 'Invalid username/password',
            '102' => 'Sender not exist',
            '103' => 'Receiver not exist',
            '104' => 'Invalid route',
            '105' => 'Invalid message type',
            '106' => 'SMS content not exist',
            '107' => 'Transaction template mismatch',
            '108' => 'Low credits in the specified route',
            '109' => 'Account is not eligible for API',
            '110' => 'Promotional route will be working from 9am to 9pm only');

        $errorcodes = array(101, 102, 103, 104, 105, 106, 107, 108, 109, 110);

        $domain = "sms.edusparrow.com";
        $username = urlencode($smsprovider->username);
        $password = urlencode($smsprovider->password);
        $sender = urlencode($smsprovider->sender);
        $message = urlencode($message);
        $parameters = "uname=$username&password=$password&sender=$sender&receiver=$number&route=T&msgtype=1&sms=$message";
        $fp = fopen("http://$domain/httpapi/smsapi?$parameters", "r");

        $response = stream_get_contents($fp);

        fpassthru($fp);
        fclose($fp);

        $error = (in_array($response, $errorcodes));
        if ($error) {
            //process only when there is error
            //$errorcode = $response;
            $errordescription = $error_description[$response];
            return 0;
        } else {
            return $response;
        }
    }

    // Report Generation for Schooler   

    public function loadClassTestSectionAction() {
        $this->tag->prependTitle("Class Test Report | ");
    }

    public function clsTestReportAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $this->view->action = $this->request->getPost('action');
    }

    public function loadSubtreeClsAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost() && $this->request->getPost('orgvalueid') != '') {
            $this->view->orgvalueid = $orgvalueid = $this->request->getPost('orgvalueid');
            $query_param = array(
                'columns' => 'org_master_id',
                'conditions' => 'parent_id = ' . $orgvalueid,
                'group' => 'org_master_id',
            );
            $this->view->action = $this->request->getPost('action');
            $this->view->org_value = OrganizationalStructureValues::find($query_param);
        }
    }

    public function loadClassTestAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = $queryParams1 = array();
        $orderphql = array();
        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                $params['subjaggregate'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        $this->view->classtest = array();
        $subjpids = $this->organizationalStructure->getAlSubjChildNodes($params['aggregateids']);
        //$subjids = $this->organizationalStructure->getGrpSubjMasPossiblities($params['aggregateids']);
        $subjids = $this->organizationalStructure->getSujMasByCombi($params['aggregateids']);

        if (count($subjids) > 0) {
            $queryParams[] = 'grp_subject_teacher_id IN(' . implode(',', $subjids) . ')';
        }
        if (count($params['subjaggregate']) > 0):
            $subjagg = $this->organizationalStructure->getAllSubjectAndSubModules($params['subjaggregate']);
        endif;
        if (count($subjagg) > 0):
            $queryParams[] = 'subjct_modules IN(' . implode(',', $subjagg) . ')';
        endif;

        if (count($queryParams) == 0) {
            $currectacdyr = $this->cycles->getCurrentAcademicYear();
            $currentclassroom = ClassroomMaster::find('find_in_set(' . $currectacdyr->id . ' , replace(aggregated_nodes_id,"-",",") )');
            $clid = $query = $subteachid = array();
            if (count($clid) > 0) {
                foreach ($currentclassroom as $curcs) {
                    $clid[] = $curcs->id;
                }
                $query[] = 'classroom_master_id IN (' . implode(',', $clid) . ')';
                $assignedCount = GroupSubjectsTeachers::find(implode(' and ', $query));
                if (count($assignedCount) > 0) {
                    foreach ($assignedCount as $assignedclass) {
                        $subteachid[] = $assignedclass->id;
                    }
                    $queryParams[] = 'grp_subject_teacher_id IN (' . implode(',', $subteachid) . ')';
                }
            }
        }

        $conditionvals = (count($queryParams) > 0) ? implode(' and ', $queryParams) : '';
        $this->view->classtest = (count($queryParams) > 0) ? ClassTest::find($conditionvals) : array();
    }

    public function clsTestListReportAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = $queryParams1 = array();
        $orderphql = array();
        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                $params['subjaggregate'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        $this->view->classtest = array();
        $subjids = $this->organizationalStructure->getSujMasByCombi($params['aggregateids']);
        if (count($subjids) > 0) {
            $queryParams[] = 'grp_subject_teacher_id IN(' . implode(',', $subjids) . ')';
        }
        if (count($params['subjaggregate']) > 0):
            $subjagg = $this->organizationalStructure->getAllSubjectAndSubModules($params['subjaggregate']);
        endif;
        if (count($subjagg) > 0):
            $queryParams[] = 'subjct_modules IN(' . implode(',', $subjagg) . ')';
        endif;
        $conditionvals = (count($queryParams) > 0) ? implode(' and ', $queryParams) : '';
//      print_r($conditionvals);exit;
        $this->view->classtest = $output = (count($queryParams) > 0) ? ClassTest::find($conditionvals) : array();

        $repArry = $this->organizationalStructure->getNameForKeys(implode(',', $params['subjaggregate']));
        $this->view->pdfHead = 'Class Test Report :' . implode(' - ', $repArry);
    }

    public function loadSubjectsListAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();

        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }

        $subjpids = $this->organizationalStructure->getAlSubjChildNodes($params['aggregateids']);
        $subjects = count($subjpids) > 0 ? $this->organizationalStructure->getAllPossibleSubjectsold($subjpids) : array();
        $this->view->subjects = $subjects;
        $this->view->params = $params;
    }

    public function loadSubjectsListRatingAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();

        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {

                $params[$key] = $value;
            }
        }

        $subjpids = $this->organizationalStructure->getAlSubjChildNodes($params['aggregateids']);
        $subjids = $this->organizationalStructure->getGrpSubjMasPossiblitiesold($params['aggregateids']);
        $subjects = count($subjpids) > 0 ? $this->organizationalStructure->getAllPossibleSubjectsold($subjpids) : array();
        $this->view->subjects = $subjects;
        $this->view->params = $params;
    }

    public function clsTestReportDetAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = $clststQury = array();

        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                $params['subjaggregate'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        $classroomname = $this->organizationalStructure->getNameForKeys(implode(',', $params['aggregateids']));
        $this->view->classroomname = str_replace('>>', ' ', implode(', ', $classroomname));
        if (isset($params['classtestid'])) {
            $clstest = array();
            $res = isset($params['classtestid']) ? $this->clsTestReportArr($params) : '';
            $this->view->subjects = $res['subjects'];
            $this->view->stumapdet = $res['stumapdet'];
            $this->view->clstest = $clstest = $res['clstest'];
            $this->view->pdfHead = 'Class Test Report :' . implode(' - ', $classroomname);
        }
    }

    public function loadAssignmentSectionAction() {
        $this->tag->prependTitle("Assignment Report | ");
    }

    public function loadAssignmentReportAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $this->view->action = $this->request->getPost('action');
    }

    public function loadSubtreeforassignmentAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost() && $this->request->getPost('orgvalueid') != '') {
            $this->view->orgvalueid = $orgvalueid = $this->request->getPost('orgvalueid');
//            $this->view->org_value = $org_value = OrganizationalStructureValues::find('parent_id = ' . $orgvalueid
//                            . ' GROUP BY  org_master_id ');
            $query_param = array(
                'columns' => 'org_master_id',
                'conditions' => 'parent_id = ' . $orgvalueid,
                'group' => 'org_master_id',
            );
            $this->view->action = $this->request->getPost('action');
            $this->view->org_value = OrganizationalStructureValues::find($query_param);
        }
    }

    public function loadSubjectsforassignmentAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();
        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {

                $params[$key] = $value;
            }
        }
        $subjpids = $this->organizationalStructure->getAlSubjChildNodes($params['aggregateids']);
        $subjects = count($subjpids) > 0 ? $this->organizationalStructure->getAllPossibleSubjectsold($subjpids) : array();
        $this->view->subjects = $subjects;
        $this->view->params = $params;
    }

    public function loadassignmentAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = $queryParams1 = array();
        $orderphql = array();
        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                $params['subjaggregate'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }

        $subjpids = $this->organizationalStructure->getAlSubjChildNodes($params['aggregateids']);
        $subjids = $this->organizationalStructure->getGrpSubjMasPossiblities($subjpids);
        if (count($subjids) > 0) {
            $queryParams[] = 'grp_subject_teacher_id IN(' . implode(',', $subjids) . ')';
        }
        if (isset($params['subjaggregate']) && count($params['subjaggregate'])):
            $subjectQury = preg_filter('/^([\d])*/', '(FIND_IN_SET("$0" ,REPLACE(subjct_modules , "-", "," )) >0)', $params['subjaggregate']);
            $queryParams[] = implode(' and ', $subjectQury);
        endif;


        if (count($queryParams) == 0) {
            $currectacdyr = $this->cycles->getCurrentAcademicYear();
            $currentclassroom = ClassroomMaster::find('find_in_set(' . $currectacdyr->id . ' , replace(aggregated_nodes_id,"-",",") )');
            $clid = $query = $subteachid = array();
            if (count($clid) > 0) {
                foreach ($currentclassroom as $curcs) {
                    $clid[] = $curcs->id;
                }
                $query[] = 'classroom_master_id IN (' . implode(',', $clid) . ')';
                $assignedCount = GroupSubjectsTeachers::find(implode(' and ', $query));
                if (count($assignedCount) > 0) {
                    foreach ($assignedCount as $assignedclass) {
                        $subteachid[] = $assignedclass->id;
                    }
                    $queryParams[] = 'grp_subject_teacher_id IN (' . implode(',', $subteachid) . ')';
                }
            }
        }

        $queryParams[] = 'is_evaluation = 1';
        $conditionvals = (count($queryParams) > 0) ? implode(' and ', $queryParams) : '';
        $this->view->assignment = (count($queryParams) > 1) ? AssignmentsMaster::find($conditionvals) : array();
    }

    public function loadAssignmentDetAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();

        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                $params['subjaggregate'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        $this->view->params = $params;
        $classroomname = $this->organizationalStructure->getNameForKeys(implode(',', $params['aggregateids']));
        $this->view->classroomname = str_replace('>>', ' ', implode(', ', $classroomname));
        $resarr = $params['assignment_id'] ? $this->getAssignArr($params) : '';
        $this->view->tests = $resarr[1];
        $this->view->studentMarkList = $resarr[0];
        $this->view->pdfHead = 'Assignment Report :' . implode(' - ', $classroomname);
    }

    public function loadAssignmentListDetailsAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = $queryParams1 = array();
        $orderphql = array();
        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                $params['subjaggregate'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }

        $subjpids = $this->organizationalStructure->getAlSubjChildNodes($params['aggregateids']);
        //$subjids = $this->organizationalStructure->getGrpSubjMasPossiblities($subjpids);
        $subjids = $this->organizationalStructure->getSujMasByCombi($params['aggregateids']);

        if (count($subjids) > 0) {
            $queryParams[] = 'grp_subject_teacher_id IN(' . implode(',', $subjids) . ')';
        }
        if (isset($params['subjaggregate']) && count($params['subjaggregate'])):
            $subjectQury = preg_filter('/^([\d])*/', '(FIND_IN_SET("$0" ,REPLACE(subjct_modules , "-", "," )) >0)', $params['subjaggregate']);
            $queryParams[] = implode(' and ', $subjectQury);
        endif;

        $queryParams[] = 'is_evaluation = 1';
        $conditionvals = (count($queryParams) > 0) ? implode(' and ', $queryParams) : '';
        $this->view->assignment = (count($queryParams) > 1) ? AssignmentsMaster::find($conditionvals) : array();
        $reportHead = $this->organizationalStructure->getNameForKeys(implode(',', $params['aggregateids']));
        $this->view->pdfHead = 'Assignment Report :' . implode(' - ', $reportHead);
    }

    public function getAssignArr($params) {
        $res = $this->organizationalStructure->buildStudentQuery(implode(',', $params['aggregateids']));
        $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,stumap.status'
                . ' FROM StudentHistory stumap '
                . ' LEFT JOIN StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id '
                . ' WHERE '
                . '  (' . implode(' or ', $res) . ') '
                . ' ORDER BY stuinfo.Student_Name ASC';
        $students = $this->modelsManager->executeQuery($stuquery);
        $i = 0;
        $cosolidatedMarks = array();
        if (count($students) > 0) {
            foreach ($students as $student) {
                $classTests = AssignmentsMaster::findFirstById($params['assignment_id']);
                $obtainedOutOf = AssignmentMarks::findFirst('assignment_id = ' . $params['assignment_id']);
                $clsTstMarks = AssignmentMarks::findFirst('assignment_id = ' . $params['assignment_id']
                                . ' and student_id = ' . $student->student_info_id);
                $obtainedmark = ($clsTstMarks->marks) ? $clsTstMarks->marks : 0;
                $cosolidatedMarks[] = array(
                    'ObtainedMarks' => $obtainedmark,
                    'studentid' => $student->student_info_id,
                    'aggregate_key' => $student->aggregate_key,
                    'name' => $student->Student_Name,
                );
                $yaxis = array(
                    'assignmenttopic' => $classTests->topic,
                    'outoff' => $obtainedOutOf->outof
                );
            }
        }
        return array($cosolidatedMarks, $yaxis);
    }

    public function clsTestReportArr($params) {
        $subjpids = $this->organizationalStructure->getAlSubjChildNodes($params['aggregateids']);
        $subjids = $this->organizationalStructure->getGrpSubjMasPossiblitiesold($params['aggregateids']);
        $subjects = count($subjids) > 0 ? $this->organizationalStructure->getAllPossibleSubjectsold($subjpids) : array();
        $res['subjects'] = $subjects;
        $subj_Ids = array();
        foreach ($subjects as $nodes) {
            $subj_Ids[] = $nodes->id;
        }
        $res['subj_Ids'] = $subj_Ids;
        if (count($subjids) > 0)
            $clststQury[] = " grp_subject_teacher_id IN (" . implode(' , ', $subjids) . ")";

        if (count($params['subjaggregate']) > 0) {

            $subjagg = $this->organizationalStructure->getAllSubjectAndSubModules($params['subjaggregate']);

            if (count(subjagg) > 0):
                $clststQury[] = 'subjct_modules IN(' . implode(',', $subjagg) . ')';
            endif;
        }else {
            if (count($subj_Ids) > 0)
                $clststQury[] = "  subjct_modules IN (" . implode(' , ', $subj_Ids) . ")";
        }


        $res = $this->organizationalStructure->buildStudentQuery(implode(',', $params['aggregateids']));
        $stuquery = 'SELECT stumap.id,stumap.student_info_id,stumap.aggregate_key,'
                . 'stumap.status, stuinfo.Phone, stuinfo.f_phone_no_status,
                    stuinfo.f_phone_no_status,stuinfo.f_phone_no,
                        stuinfo.m_phone_no_status,stuinfo.m_phone_no,
                        stuinfo.g_phone_no_status,stuinfo.g_phone,stuinfo.Student_Name '
                . ' FROM StudentHistory stumap LEFT JOIN'
                . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE  '
                . '(' . implode(' and ', $res) . ') ORDER BY stuinfo.Student_Name ASC';

        $res['stumapdet'] = $students = $this->modelsManager->executeQuery($stuquery);
        if ($params['classtestid'] == '') {
            $res['clstest'] = $clstest = ClassTest::find(implode(' and ', $clststQury));
        } else {
            $res['clstest'] = $clstest = ClassTest::find('class_test_id IN(' . $params['classtestid'] . ')');
        }

        return $res;
    }

    public function loadSubtreeSubjAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost() && $this->request->getPost('orgvalueid') != '') {
            $this->view->orgvalueid = $orgvalueid = $this->request->getPost('orgvalueid');
            $this->view->action = $this->request->getPost('action');
            $query_param = array(
                'columns' => 'org_master_id',
                'conditions' => 'parent_id = ' . $orgvalueid,
                'group' => 'org_master_id',
            );
            $this->view->org_value = OrganizationalStructureValues::find($query_param);
        }
    }

    public function printAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
    }

    public function sendMessageToStudentsClsTestAction() {
        $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
        $params = $queryParams = array();

        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                $params['subjaggregate'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        $res = $this->clsTestReportArr($params);
        $stumapdet = $res['stumapdet'];
        $clstest = $res['clstest'];
        $template = 'Your child ##Student Name##  class test marks in ##Subject## ##Marks##';

        $failed_sms = 0;
        $no_of_msgs = 0;
        $total_msg = 0;
        $header = array('Name', 'Mobile No', 'Message', 'Date', 'Status', 'Smsid');
        $dateval = date('dmYHis');
        $filename = 'CLASS_TEST_REPORT_' . $dateval . '.csv';
        $fp = fopen(DOWNLOAD_DIR . $filename, 'a');
        $delimiter = ",";
        fputcsv($fp, $header, $delimiter);
        if (count($stumapdet) > 0) {
            foreach ($stumapdet as $stu) {
                $stu_data = array();
                $check_arr_msg = '';
                $check_arr_data = array();
                $check_arr_data['Name'] = $stu->Student_Name;
                $phoneno = ($stu->f_phone_no_status == 1) ? $stu->f_phone_no :
                        ( ($stu->m_phone_no_status == 1) ? $stu->m_phone_no :
                                (($stu->g_phone_no_status == 1) ? $stu->g_phone : $stu->Phone));
                $check_arr_data['phone'] = $phoneno;
                $orgvaldet = OrganizationalStructureValues::find(array(
                            "columns" => 'group_concat(name) as subjnames',
                            " id  IN (" . implode(' , ', $params['subjaggregate']) . ")"
                ));
                $aggregate_keys = count($orgvaldet) > 0 ? ($orgvaldet[0]->subjnames != '' ? (explode(',', $orgvaldet[0]->subjnames)) : '') : '';
                $check_arr_data['subject'] = implode('>>', $aggregate_keys);
                foreach ($clstest as $tst) {
                    $stumark = ClassTestMarks::findFirst('class_test_id = ' . $tst->class_test_id
                                    . ' and student_id =' . $stu->student_info_id);

                    $check_arr_data['message'][] = $tst->class_test_name . '-' . ($stumark->marks ? ($stumark->marks . '/' . $stumark->outof) : '');
                }
                $check_arr_msg['smstxt'] = "Your child " . $check_arr_data['Name']
                        . " class test marks in "
                        . $check_arr_data['subject'] . " 
";
                $check_arr_msg['smstxt'] .= implode(' ', $check_arr_data['message']);

                $stu_data[] = $stu->Student_Name;
                $stu_data[] = $check_arr_data['phone'];
                $stu_data[] = $check_arr_msg['smstxt'];
                $stu_data[] = date('d-m-Y');

//                $return = $this->sendSMSByGateway($check_arr_data['phone'], $check_arr_msg['smstxt']);
                $return = ControllerBase::sendSMSByGateway($check_arr_data['phone'], $check_arr_msg['smstxt']);
                $sentsmsstatus = new SentSmsResults();
                $sentsmsstatus->user_id = $stu->student_info_id;
                $sentsmsstatus->message = $template;
                $sentsmsstatus->date = time();
                if ($return == 0) {
                    $stu_data[] = 'Failed';
                    $sentsmsstatus->message_status = 'Failed';
                    $failed_sms++;
                } else {
                    $stu_data[] = 'Success';
                    $sentsmsstatus->message_status = 'Success';
                    //divide the message length by 160, 1 msg = 160 characters
                    $total_msg = round(strlen($check_arr_msg['smstxt']) / 160);
                    $no_of_msgs = $no_of_msgs + $total_msg;
                }
                if (preg_match('/^[0-9]*$/', $return)) {
                    $stu_data[] = $return;
                    $sentsmsstatus->responseid = $return;
                } else {
                    $stu_data[] = '';
                    $sentsmsstatus->responseid = '';
                }

                $fp = fopen(DOWNLOAD_DIR . $filename, 'a');
                fputcsv($fp, $stu_data, $delimiter);

                $sentsmsstatus->save();
//                $check_arr[] = $check_arr_msg;
//                break;
            }
            fclose($fp);
//            echo '<pre>';
//                print_r($check_arr_data);exit;
        }
        $newtemplate = SmsTemplates::findFirst('temp_content = "' . $template . '"') ?
                SmsTemplates::findFirst('temp_content = "' . $template . '"') :
                new SmsTemplates();
        $newtemplate->temp_content = $template;
        $newtemplate->temp_status = 'Approved';
        $newtemplate->tags = 'template';
        $newtemplate->is_active = 1;
        $newtemplate->save();

        $sentsms = new SentSms();
        $sentsms->template_id = $newtemplate->temp_id;
        $sentsms->time = time();
        $sentsms->number_of_recipients = count($stumapdet);
        $sentsms->number_of_failures = $failed_sms;
        $sentsms->number_of_messages = $no_of_msgs;
        $sentsms->report_file = $filename;
        $sentsms->save();


        if ($no_of_msgs > 0) {
            $message['type'] = 'success';
            $message['message'] = '<div class="alert alert-block alert-success fade in">Message sent successfully!</div>'; // $appln->status . ' Successfully!';
            print_r(json_encode($message));
            exit;
        } else {
            $message['type'] = 'success';
            $message['message'] = '<div class="alert alert-block alert-success fade in">Message sending failed..!</div>'; // $appln->status . ' Successfully!';
            print_r(json_encode($message));
            exit;
        }
//        echo '<pre>';
//        print_r($check_arr);
//        exit;
    }

    public function loadHomeWorkSectionAction() {
        $this->tag->prependTitle("Homework Report | ");
    }

    public function stuSearchAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->getPost('action') != '') {
            $this->view->action = $this->request->getPost('action');
            $this->view->acdyrMas = $acdyrMas = OrganizationalStructureMaster::findFirst('mandatory_for_admission =1 and cycle_node =1');
            $this->view->nodes = $this->_getNonMandNodesForAssigning($acdyrMas->id);
            $this->view->mandnode = $this->_getMandNodesForAssigning($acdyrMas);
        }
    }

    public function loadHomeWorkBYClassAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost()) {
            $params = $queryParams = $queryParams1 = array();
            $orderphql = array();
            foreach ($this->request->getPost() as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv[0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else {

                    $params[$key] = $value;
                }
            }
            $classroomname = $this->organizationalStructure->getNameForKeys(implode(',', $params['aggregateids']));
            $this->view->classroomname = str_replace('>>', ' ', implode(', ', $classroomname));
//            echo '<pre>';
            $subjpids = $this->organizationalStructure->getAlSubjChildNodes($params['aggregateids']);
            $subjids = $this->organizationalStructure->getSujMasByCombi($params['aggregateids']);
            $subjects = count($subjids) > 0 ? $this->organizationalStructure->getAllPossibleSubjects($subjids) : array();

            $this->view->subjects = $subjects;
            $subj_Ids = array();
            foreach ($subjects as $nodes) {
                $subj_Ids[] = $nodes->id;
            }

            if (isset($params['hdate']) && $params['hdate'] != '') {
                $queryParams[] = 'hmwrkdate >= "' . strtotime($params['hdate'] . ' 00:00:00')
                        . '" and hmwrkdate <= "' . strtotime($params['hdate'] . ' 23:59:59') . '"';
            }
            if (count($subj_Ids) > 0) {
                $queryParams[] = 'subjct_modules IN(' . implode(',', $subj_Ids) . ')';
            }
            if (count($subjids) > 0) {
                $queryParams[] = 'grp_subject_teacher_id IN(' . implode(',', $subjids) . ')';
            }
            $conditionvals = (count($queryParams) > 0) ? implode(' and ', $queryParams) : '';
            $this->view->homework_value = (count($subj_Ids) > 0) ? HomeWorkTable::find($conditionvals) : 0;

            $repArry = $this->organizationalStructure->getNameForKeys(implode(',', $params['aggregateids']));
            $this->view->pdfHead = 'Homework Report :' . implode(' - ', $repArry);
        }
    }

    public function loadHWFullReportAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost()) {
            $params = $queryParams = $queryParams1 = array();
            $orderphql = array();
            foreach ($this->request->getPost() as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv[0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else {

                    $params[$key] = $value;
                }
            }
            $classroomname = $this->organizationalStructure->getNameForKeys(implode(',', $params['aggregateids']));
            $this->view->classroomname = str_replace('>>', ' ', implode(', ', $classroomname));
//            echo '<pre>';
            $subjpids = $this->organizationalStructure->getAlSubjChildNodes($params['aggregateids']);
            $subjids = $this->organizationalStructure->getSujMasByCombi($params['aggregateids']);
            $subjects = count($subjids) > 0 ? $this->organizationalStructure->getAllPossibleSubjects($subjids) : array();

            $this->view->subjects = $subjects;
            $subj_Ids = array();
            foreach ($subjects as $nodes) {
                $subj_Ids[] = $nodes->id;
            }

            if (isset($params['homework_date']) && $params['homework_date'] != '') {
                $date_array = explode('to', $params['homework_date']);
                $queryParams[] = 'hmwrkdate >= "' . strtotime(trim($date_array[0]) . ' 00:00:00')
                        . '" and hmwrkdate <= "' . strtotime(trim($date_array[1]) . ' 23:59:59') . '"';
            }
            if (count($subj_Ids) > 0) {
                $queryParams[] = 'subjct_modules IN(' . implode(',', $subj_Ids) . ')';
            }
            if (count($subjids) > 0) {
                $queryParams[] = 'grp_subject_teacher_id IN(' . implode(',', $subjids) . ')';
            }
            $conditionvals = (count($queryParams) > 0) ? implode(' and ', $queryParams) : '';
            $this->view->homework_value = (count($subj_Ids) > 0) ? HomeWorkTable::find($conditionvals) : 0;

            $repArry = $this->organizationalStructure->getNameForKeys(implode(',', $params['aggregateids']));
            $this->view->pdfHead = 'Homework Report :' . implode(' - ', $repArry);
        }
    }

    public function sendHomeWorkReportAction() {
        $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
        if ($this->request->isPost()) {

            $params = $queryParams = $queryParams1 = array();
            $orderphql = array();
            foreach ($this->request->getPost() as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv[0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else {

                    $params[$key] = $value;
                }
            }
            $classroomname = ControllerBase::getNameForKeys(implode(',', $params['aggregateids']));
            $this->view->classroomname = str_replace('>>', ' ', implode(', ', $classroomname));
//            echo '<pre>';
            $subjpids = ControllerBase::getAlSubjChildNodes($params['aggregateids']);
            $subjids = ControllerBase::getSujMasByCombi($params['aggregateids']);
            $subjects = count($subjids) > 0 ? ControllerBase::getAllPossibleSubjects($subjids) : array();

            $this->view->subjects = $subjects;
            $subj_Ids = array();
            foreach ($subjects as $nodes) {
                $subj_Ids[] = $nodes->id;
            }
            if (isset($params['hdate']) && $params['hdate'] != '') {
                $queryParams[] = 'hmwrkdate >= "' . strtotime($params['hdate'] . ' 00:00:00')
                        . '" and hmwrkdate <= "' . strtotime($params['hdate'] . ' 23:59:59') . '"';
            }
            if (count($subj_Ids) > 0) {
                $queryParams[] = 'subjct_modules IN(' . implode(',', $subj_Ids) . ')';
            }
            if (count($subjids) > 0) {
                $queryParams[] = 'grp_subject_teacher_id IN(' . implode(',', $subjids) . ')';
            }
            $conditionvals = (count($queryParams) > 0) ? implode(' and ', $queryParams) : '';
//                print_r($conditionvals);
//                exit;
            $homework_value = (count($subj_Ids) > 0) ? HomeWorkTable::find($conditionvals) : 0;


            $homeworknodename = ControllerBase::getMandNameForKeys(implode(',', $params['aggregateids']));
//                print_r($homeworknodename);exit;
            foreach ($homeworknodename as $hval) {
                $c = explode('>>', ($hval));
                array_shift($c);
                $hname[] = implode(' ', $c);
            }

            if ($homework_value != '' && count($homework_value) > 0) {
                $message_content = 'Home Work (' . implode(', ', array_filter($hname)) . " )
";
                $send_msg_content = 'Home Work (' . implode(', ', array_filter($hname)) . ' <br>';
                foreach ($homework_value as $homework_val) {

                    $template = preg_replace("/[\r\n]+/", "\n", $homework_val->homework);
                    $template = preg_replace("/\s+/", ' ', $template);
                    $send_msg_content .= $template;

                    $orgvaldet = OrganizationalStructureValues::findFirstById($homework_val->subjct_modules);
//                    print_r($orgvaldet); exit;
                    $aggregate_keys = HomeWorkController::_getMandNodesForExam($orgvaldet);
                    $aggregate_keys = array_reverse($aggregate_keys);
                    $message_content .= implode('-', $aggregate_keys) . ' - ';
                    $message_content .= $homework_val->homework . " 
";
                    $send_msg_content .= implode('-', $aggregate_keys) . ' - ';
                    $send_msg_content .= $template . ' <br>';
                }

                $subjQuery = preg_filter('/^([\d,])*/', '(find_in_set("$0", aggregate_key)>0)', $params['aggregateids']);
//                echo 'test';
//                exit;
//            $params['aggregateids']
                $studet = StudentMapping::find(implode(' AND ', $subjQuery));
//            print_r(implode(' AND ', $subjQuery));
//            exit;
                $phoneno_arr = array();
                foreach ($studet as $studetval) {
                    $studata = StudentInfo::findFirstById($studetval->student_info_id);

                    $phoneno = $studata->Phone;

                    if ($studata->f_phone_no_status == 1)
                        $phoneno = $studata->f_phone_no;

                    if ($studata->m_phone_no_status == 1)
                        $phoneno = $studata->m_phone_no;

                    if ($studata->g_phone_no_status == 1)
                        $phoneno = $studata->g_phone;

                    $phoneno_arr[] = $phoneno;
                }

                $phoneno_arr = array();
                $phoneno_arr[] = 9843258019;
                $phoneno_arr[] = 9551552141;

                $header = array();
                $header[] = 'Name';
                $header[] = 'Mobile No';
                $header[] = 'Message';
                $header[] = 'Date';
                $header[] = 'Status';
                $header[] = 'Smsid';
                $dateval = date('d-m-Y H:i:s');


                $data = preg_replace('/[ ]/', '_', $dateval);
                $data = preg_replace('/[:]/', '-', $dateval);
//         print_r($data);
//         exit; 

                $filename = 'HOME_WORK_REPORT_' . $data . '.csv';

                $fp = fopen(DOWNLOAD_DIR . $filename, 'a');

                $delimiter = ",";

                fputcsv($fp, $header, $delimiter);

                $number = implode(',', $phoneno_arr);
                $stu_data = array();
                $stu_data[] = 'HOME WORK';
                $stu_data[] = $number;
                $stu_data[] = $send_msg_content;
                $stu_data[] = date('d-m-Y');
//                echo $message_content;
//exit;
//                $return = $this->sendSMSByGateway($number, $message_content);

                $return = ControllerBase::sendSMSByGateway($number, $message_content);
//                    print_r($stu_data);
//                    echo $return;exit;
                $total_msg = $no_of_msgs = $failed_sms = 0;

                if ($return == 0) {
                    $stu_data[] = 'Failed';
                    $failed_sms++;
                } else {
                    $stu_data[] = 'Success';

                    //divide the message length by 160, 1 msg = 160 characters
                    $total_msg = round(strlen($message_content) / 160);
                    $no_of_msgs = $no_of_msgs + $total_msg;
                }


                if (preg_match('/^[0-9]*$/', $return)) {
                    $stu_data[] = $return;
                } else {
                    $stu_data[] = '';
                }

                $fp = fopen(DOWNLOAD_DIR . $filename, 'a');
                fputcsv($fp, $stu_data, $delimiter);
                fclose($fp);
                $newtemplate = new SmsTemplates();
                $newtemplate->temp_content = $message_content;
                $newtemplate->temp_status = 'Approved';
                $newtemplate->tags = 'template';
                $newtemplate->is_active = 1;
                $newtemplate->save();

                $sentsms = new SentSms();
                $sentsms->template_id = $newtemplate->temp_id;
                $sentsms->time = time();
                $sentsms->number_of_recipients = count($phoneno_arr);
                $sentsms->report_file = '';
                $sentsms->number_of_failures = $failed_sms;
                $sentsms->number_of_messages = $no_of_msgs; //count($phoneno_arr);
                $sentsms->report_file = $filename;
                $sentsms->responseid = $return;
                $sentsms->save();

                if ($return == 0) {
                    $message['type'] = 'error';
                    $message['message'] = '<div class="alert alert-block alert-danger fade in">Home Work Sending Failed.</div>';
                    print_r(json_encode($message));
                    exit;
                } else {

                    $message['type'] = 'success';
                    $message['message'] = '<div class="alert alert-block alert-success fade in">Home Work Send Successfully.</div>';
                    print_r(json_encode($message));
                    exit;
                }
            } else {

                $message['type'] = 'error';
                $message['message'] = '<div class="alert alert-block alert-danger fade in">Home Work Not Found.</div>';
                print_r(json_encode($message));
                exit;
            }
        }
    }

    public function ratingReportAction() {
        
    }

    public function loadStudentsRatingListAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();

        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                $params['subjaggregate'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        $classroomname = $this->organizationalStructure->getNameForKeys(implode(',', $params['aggregateids']));

        $this->view->classroomname = str_replace('>>', ' ', implode(', ', $classroomname));
        $res = AcademicReportsController::getSubjRatArr($params);
        $this->view->rowArr = $res[1];
        $this->view->yaxisuniqueArr = $res[0];
    }

    public function getSubjRatArr($params) {
        $subjpids = ControllerBase::getAlSubjChildNodes($params['aggregateids']);
        $subjids = ControllerBase::getGrpSubjMasPossiblitiesold($params['aggregateids']);
        $subjects = count($subjids) > 0 ? ControllerBase::getAllPossibleSubjectsold($subjpids) : array();
        $this->view->subjects = $subjects;
        $subj_Ids = array();
        foreach ($subjects as $nodes) {
            $subj_Ids[] = $nodes->id;
        }
        $res = ControllerBase::buildStudentQuery(implode(',', $params['aggregateids']));
        $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                . 'stumap.status'
                . ' FROM StudentHistory stumap LEFT JOIN'
                . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE '
                . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
        $students = $this->modelsManager->executeQuery($stuquery);
        $rowArr = $yaxis = array();
        foreach ($students as $stu) {
            $ratingCategorys = RatingCategoryMaster::find();
            foreach ($ratingCategorys as $ratingCategory) {
                $ratingPoints = 0;
                $ratingValues = RatingCategoryValues::find('rating_category = ' . $ratingCategory->id);
                $ratTotPoints = $ratingCategory->category_weightage;
                foreach ($ratingValues as $rvalue) {
                    $clststQury = array();
                    if (count($subjids) > 0)
                        $clststQury[] = count($subjids) > 0 ? ("  subject_master_id IN (" . implode(' , ', $subjids) . ")") : '';
                    if (count($params['subjaggregate']) > 0) {
                        $subjagg = ControllerBase::getAllSubjectAndSubModules($params['subjaggregate']);
                        if (count(subjagg) > 0):
                            $clststQury[] = 'subjct_modules IN(' . implode(',', $subjagg) . ')';
                        endif;
                    }else {
                        if (count($subj_Ids) > 0)
                            $clststQury[] = "  subjct_modules IN (" . implode(' , ', $subj_Ids) . ")";
                    }
                    $clststQury[] = 'student_id = ' . $stu->student_info_id;
                    $clststQury[] = '  rating_division_id =' . $params['rating_name'];
                    $clststQury[] = '  rating_category =' . $ratingCategory->id;
                    $clststQury[] = '  rating_value = ' . $rvalue->id;
                    $studentRating = StudentSubteacherRating::findFirst(implode(' and ', $clststQury));

                    if ($studentRating && $studentRating->rating_id > 0) {
                        $ratingPoints += ($rvalue->rating_level_value / 100) * $ratingCategory->category_weightage;
                    }
                }
                $rowArr[$stu->student_info_id][$ratingCategory->id] = array(
                    'rating_name' => $ratingCategory->category_name,
                    'ratingPoints' => $ratingPoints,
                    'StudentName' => $stu->Student_Name,
                    'StudentID' => $stu->student_info_id,
                    'aggreegatekey' => $stu->aggregate_key
                );
                $yaxis[$ratingCategory->id] = $ratingCategory->category_name . " ($ratTotPoints pts)";

                $yaxis[$ratingCategory->id] = array(
                    'categoryname' => $ratingCategory->category_name,
                    'ratPoints' => $ratTotPoints);
            }
        }
        return array($yaxis, $rowArr, $subj_Ids);
    }

    public function getSubjectRatingReportAction() {
        $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
        $params = $queryParams = array();
        $aggregateval = '';
        $ratingdata = json_decode($this->request->getPost('params'));
        foreach ($ratingdata as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                $params['subjaggregate'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        $res = $this->getSubjRatArr($params);
        $rowArr = $res[1];
        $yaxis = $res[0];
        $subj_Ids = $res[2];
        $acdyrMas = OrganizationalStructureMaster::findFirst('mandatory_for_admission = 1 and cycle_node = 1');
        $mandnode = AcademicReportsController::_getMandNodesForAssigning($acdyrMas);
        $orgvaldet = OrganizationalStructureValues::findFirst("id IN (" . implode(' , ', $subj_Ids) . ")");
        $subject_keys = $this->_getMandNodesForSubject($orgvaldet);
        $subject_keys = array_reverse($subject_keys);
        $header[] = 'Student Name';
        $clsscnt = 0;
        foreach ($mandnode as $node) {
            if ($clsscnt != 0) {
                $header[] = ucfirst($node);
            }
            $clsscnt++;
        }

        foreach ($yaxis as $yaxisval) {
            $header[] = $yaxisval['categoryname'];
        }
        $subject_head = array();
        $icnt = 0;
        foreach ($header as $headerval) {
            if ($icnt == 0) {
                $subject_head[] = 'Subject : ' . implode(' >> ', $subject_keys);
            } else {
                $subject_head[] = '';
            }
            $icnt++;
        }
//       echo '<pre>'; print_r($rowArr);exit;
        $reportdata = array();
        foreach ($rowArr as $stuarr) {
            $reportval = array();
            $stu_cnt = 0;
            foreach ($stuarr as $stuarrval) {
                if ($stu_cnt == 0) {
                    $reportval[] = $stuarrval['StudentName'];

                    $aggregatevals = $stuarrval['aggreegatekey'] ? explode(',', $stuarrval['aggreegatekey']) : '';
                    $class_arr = array();
                    $aggcnt = 0;
                    if ($aggregatevals != '') {
                        foreach ($aggregatevals as $aggregateval) {
                            if ($aggcnt != 0) {
                                $orgnztn_str_det = OrganizationalStructureValues::findFirstById($aggregateval);
                                $orgnztn_str_mas_det = OrganizationalStructureMaster::findFirstById($orgnztn_str_det->org_master_id);
                                $class_arr[$orgnztn_str_mas_det->id] = $orgnztn_str_det->name ? $orgnztn_str_det->name : '-';
                            }
                            $aggcnt++;
                        }
                        $aggcntval = 0;
                        foreach ($mandnode as $key => $mandnodeval) {
                            if ($aggcntval != 0) {
                                $reportval[] = isset($class_arr[$key]) ? $class_arr[$key] : '-';
                            }
                            $aggcntval++;
                        }
                    }
                }
                $reportval[] = $stuarrval['ratingPoints'];
                $stu_cnt++;
            }
            $reportdata[] = $reportval;
        }

        $filename = 'Student_Rating_List_' . date('d-m-Y') . '.csv';

        $param['filename'] = $filename;
        $param['header'] = $header;
        $param['data'] = $reportdata;
        $this->generateXcel($param);
    }

    public function loadStudExamChartAction() {

        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $identity = $this->auth->getIdentity();
        if ($this->request->isPost()) {
            $data = json_decode($this->request->getPost('params'));
            $params = $queryParams = array();
            foreach ($data as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv[0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else {

                    $params[$key] = $value;
                }
            }
        }
        $params['fullchart'] = '1';
        $chartParams = $this->_getMainExamPercentage($params);
        $per = $chartParams[0];
        $clsper = $chartParams[1];
        if (count($chartParams[2]) < 1):
            echo '<div class="alert alert-block alert-danger fade in">Exams not yet conducted!</div>';
            exit;
        endif;
        if (array_sum($clsper) == 0):
            echo '<div class="alert alert-block alert-danger fade in">Exam mark evaluation is in progress!</div>';
            exit;
        endif;
        /* if (count($per) == 0 && count($clsper) == 0):
          echo '<div class="alert alert-block alert-danger fade in">Main exams not yet added!</div>';
          exit;
          endif; */
        $temp = $chartParams[2];
        $header = $chartParams[3];
        $stat = $chartParams[4];
        // $type = $chartParams[5];
        $this->tag->prependTitle("Student Dashboard | ");

        $this->view->chart = 'pie';
        $this->view->header = $header;
        $this->view->stat = $stat;
        $categorieswithqoutes = array();
        $this->view->categories = '';
        $this->view->studentaverage = (is_array($per)) ? implode(',', $per) : '';
        $this->view->classaverage = (is_array($clsper)) ? implode(',', $clsper) : '';
        $this->view->space = '';
        $this->view->blue = 'Class Average';
        $this->view->red = $student_name . ' Average';
        if (is_array($temp)) {
            for ($i = 0; $i < count($temp); $i++) {
                $categorieswithqoutes[$i] = '"' . $temp[$i] . '"';
            }
            $this->view->categories = implode(',', $categorieswithqoutes);
        }
    }

    public function _getMainExamPercentage($params) {
        $student_id = $params['student_id'];
        $aggregate_id = $params['aggregateids'];
        $stuQury = preg_filter('/^([\d])*/', '(FIND_IN_SET("$0" ,stumap.aggregate_key) >0)', $aggregate_id);

        $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                . 'stumap.subordinate_key,stumap.status'
                . ' FROM StudentMapping stumap LEFT JOIN'
                . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                . '(' . implode(' and ', $stuQury) . ') ORDER BY stuinfo.Student_Name ASC';
        $this->view->students = $students = $this->modelsManager->executeQuery($stuquery);

        $cosolidatedMarks = array();
        $studentpercentforchart = array();
        $classpercentforchart = array();
        $yaxis = array();
        $student_name = StudentInfo::findFirstById($params['student_id'])->Student_Name . "\'" . "s";
        $res = ControllerBase::buildExamQuery(implode(',', $params['aggregateids']));
        $mainexams = Mainexam::find(implode(' or ', $res));

        $studentid = array();
        if (count($students) > 0):
            foreach ($students as $classStudent) {

                $studentid[] = $classStudent->student_info_id;
            }
        endif;
        $student_ids = implode(',', $studentid);

        foreach ($mainexams as $mainexam) {

            $overalstutotalmarks = $overalstuout = $overalclstotalmarks = $overalclsout = 0;
            $totclsout = $totclsmarks = $totalMarks = $totalOutOf = 0;
//            $mainexMarks = MainexamMarks::findFirst('mainexam_id = ' . $mainexam->id . ' and student_id = ' . $student_id);
            $mainexsMark = MainexamMarks::find('mainexam_id = ' . $mainexam->id . ' and student_id = ' . $student_id);

            foreach ($mainexsMark as $mainexMarks) {

                $obtainedmark = (($mainexMarks->inherited_marks) ? $mainexMarks->inherited_marks : 0 ) + (($mainexMarks->marks) ? $mainexMarks->marks : 0);

                $obtainedoutOf = (($mainexMarks->inherited_outof ) ? $mainexMarks->inherited_outof : 0 ) + (($mainexMarks->outof) ? $mainexMarks->outof : 0);

                $cosolidatedMarks[$mainexam->exam_name] = array(
                    'MexamName' => $mainexam->exam_name
                );

                $cosolidatedMarks[$mainexam->exam_name]['MoutOff'] += $obtainedoutOf;
                $cosolidatedMarks[$mainexam->exam_name] ['ObtainedMarks'] += $obtainedmark;
                $totalOutOf += $obtainedoutOf;
                $totalMarks += $obtainedmark;

                if (($obtainedoutOf > 0)) {
                    $overalstutotalmarks += ($obtainedmark / $obtainedoutOf * 100);
                    $overalstuout ++;
                }
            }
            $mainexClsMarks = MainexamMarks::find('mainexam_id = ' . $mainexam->id .
                            ' and student_id IN ( ' . $student_ids . ' )');

            foreach ($mainexClsMarks as $mainexClsMark) {
                $obtainedClsmark = (($mainexClsMark->inherited_marks) ? $mainexClsMark->inherited_marks : 0 ) + (($mainexClsMark->marks) ? $mainexClsMark->marks : 0);
                $obtainedClsoutOf = (($mainexClsMark->inherited_outof ) ? $mainexClsMark->inherited_outof : 0 ) + (($mainexClsMark->outof) ? $mainexClsMark->outof : 0);

                $totclsout += $obtainedClsoutOf;
                $totclsmarks += $obtainedClsmark;
                if (($obtainedClsoutOf > 0)) {
                    $overalclstotalmarks += ($obtainedClsmark / $obtainedClsoutOf * 100);
                    $overalclsout ++;
                }
            }
//  
            // echo '<pre>';
            //            echo $mainexam->exam_name.': '.$overalstutotalmarks.'/ '.$overalstuout .'<br>';

            if ($totalOutOf != 0) {
                $overallpercent = round($totalMarks / $totalOutOf * 100, 2);
            }

            if ($totclsout != 0) {
                $overallclspercent = round($totclsmarks / $totclsout * 100, 2);
            }

            if ($overalstuout > 0) {
                $studentpercentforchart[$mainexam->exam_name] += round($overalstutotalmarks / $overalstuout, 2);
            }

            if ($overalclsout > 0) {
                $classpercentforchart[$mainexam->exam_name] += round($overalclstotalmarks / $overalclsout, 2);
                $yaxis[] = $mainexam->exam_name;
            }
        }
        $yaxisuniqueArr = array_unique($yaxis);
        $header = $student_name . ' Main Exam marks Average vs Class Average ( ' . $academicyrname . ' )';
        $stat = 'MainExam(Statistics)';
        if ($params['fullchart'] == 1)
            return array($studentpercentforchart, $classpercentforchart, $yaxisuniqueArr, $header, $stat);
        if ($params['mainTable'] == 1)
            return $cosolidatedMarks;
        if ($params['overalPercent'] == 1)
            return array($studentpercentforchart, $classpercentforchart);
    }

    public function assignmentExcelReportAction() {
        $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
        $params = $queryParams = array();
        $data = json_decode($this->request->getPost('params'));
        foreach ($data as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                $params['subjaggregate'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        $resarr = $this->getAssignArr($params);
        $yaxis = $resarr[1];
        $cosolidatedMarks = $resarr[0];
        $orgvaldet = OrganizationalStructureValues::find(array(
                    "columns" => 'group_concat(name) as subjnames',
                    " id  IN (" . implode(' , ', $params['subjaggregate']) . ")"
        ));
        $subject_keys = count($orgvaldet) > 0 ? ($orgvaldet[0]->subjnames != '' ? (explode(',', $orgvaldet[0]->subjnames)) : '') : '';
        $acdyrMas = OrganizationalStructureMaster::findFirst('mandatory_for_admission = 1 and cycle_node = 1');
        $this->mandnode = $this->_getMandNodesForAssigning($acdyrMas);
        $header[] = 'Student Name';
        $clsscnt = 0;
        foreach ($this->mandnode as $node) {
            if ($clsscnt != 0) {
                $header[] = ucfirst($node);
            }
            $clsscnt++;
        }
        $header[] = $yaxis['assignmenttopic'] . '(' . $yaxis['outoff'] . ')';
        $subject_head = array();
        $icnt = 0;
        foreach ($header as $headerval) {
            if ($icnt == 0) {
                $subject_head[] = 'Subject : ' . implode(' >> ', $subject_keys);
            } else {
                $subject_head[] = '';
            }
            $icnt++;
        }
        $reportdata = array();
        $i = 0;
        if (count($cosolidatedMarks) > 0) {
            foreach ($cosolidatedMarks as $stum) {
                $reportval = array();
                $reportval[] = $stum['name'];
                $aggregatevals = $stum['aggregate_key'] ? explode(',', $stum['aggregate_key']) : '';
                $class_arr = array();
                $aggcnt = 0;
                if ($aggregatevals != '') {
                    foreach ($aggregatevals as $aggregateval) {
                        if ($aggcnt != 0) {
                            $orgnztn_str_det = OrganizationalStructureValues::findFirstById($aggregateval);
                            $orgnztn_str_mas_det = OrganizationalStructureMaster::findFirstById($orgnztn_str_det->org_master_id);
                            $class_arr[$orgnztn_str_mas_det->id] = $orgnztn_str_det->name ? $orgnztn_str_det->name : '-';
                        }
                        $aggcnt++;
                    }
                    $aggcntval = 0;
                    foreach ($this->mandnode as $key => $mandnodeval) {
                        if ($aggcntval != 0) {
                            $reportval[] = isset($class_arr[$key]) ? $class_arr[$key] : '-';
                        }
                        $aggcntval++;
                    }
                }
                $reportval[] = $stum['ObtainedMarks'];
                $reportdata[] = $reportval;
                $i++;
            }
        }
        $filename = 'Student_Assignment_List_' . date('d-m-Y') . '.csv';
        $counter = 0;
        $unlink_file = DOWNLOAD_DIR . 'Student_Assignment_List_' . date('d-m-Y') . '.csv';
        if (file_exists($unlink_file)) {
            unlink($unlink_file);
        }
        $fp = fopen(DOWNLOAD_DIR . $filename, 'a');
        $delimiter = ",";
        $emptyarr = array();
        fputcsv($fp, $subject_head, $delimiter);
        fputcsv($fp, $emptyarr, $delimiter);
        fputcsv($fp, $emptyarr, $delimiter);
        fputcsv($fp, $header, $delimiter);
        foreach ($reportdata as $reportsval) {
            fputcsv($fp, $reportsval, $delimiter);
        }
        fclose($fp);
        $file = DOWNLOAD_DIR . $filename;
        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '";');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            ob_clean();
            flush();
            readfile($file);
            exit;
        }
    }

    public function loadSubjectmodulesAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost() && $this->request->getPost('orgvalueid') != '') {
            $this->view->orgvalueid = $orgvalueid = $this->request->getPost('orgvalueid');
            //$this->view->org_value = $org_value = OrganizationalStructureValues::find('parent_id = ' . $orgvalueid
            //               . ' GROUP BY  org_master_id ');
            $query_param = array(
                'columns' => 'org_master_id',
                'conditions' => 'parent_id = ' . $orgvalueid,
                'group' => 'org_master_id',
            );
            $this->view->org_value = OrganizationalStructureValues::find($query_param);
        }
    }

    public function assignmentSendSmsAction() {
        $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
        $params = $queryParams = array();

        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                $params['subjaggregate'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        $res = ControllerBase::buildStudentQuery(implode(',', $params['aggregateids']));
        $stuquery = 'SELECT stumap.id,stumap.student_info_id,stumap.aggregate_key,'
                . 'stumap.subordinate_key,stumap.status, stuinfo.Phone, stuinfo.f_phone_no_status,
                    stuinfo.f_phone_no_status,stuinfo.f_phone_no,
                        stuinfo.m_phone_no_status,stuinfo.m_phone_no,
                        stuinfo.g_phone_no_status,stuinfo.g_phone,stuinfo.Student_Name '
                . ' FROM StudentMapping stumap LEFT JOIN'
                . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                . '(' . implode(' and ', $res) . ') ORDER BY stuinfo.Admission_no ASC';


        $students = $this->modelsManager->executeQuery($stuquery);
        $template = 'Your child ##Student Name##  assignment  marks in ##Subject## ##Marks##';

        $failed_sms = 0;
        $no_of_msgs = 0;
        $total_msg = 0;
        $header = array('Name', 'Mobile No', 'Message', 'Date', 'Status', 'Smsid');
        $dateval = date('dmYHis');
        $filename = 'ASSIGNMENT_REPORT_' . $dateval . '.csv';
        $fp = fopen(DOWNLOAD_DIR . $filename, 'a');
        $delimiter = ",";
        fputcsv($fp, $header, $delimiter);
        if (count($students) > 0) {
            foreach ($students as $stu) {
                $stu_data = array();
                $check_arr_msg = '';
                $check_arr_data = array();
                $check_arr_data['Name'] = $stu->Student_Name;
                $phoneno = ($stu->f_phone_no_status == 1) ? $stu->f_phone_no :
                        ( ($stu->m_phone_no_status == 1) ? $stu->m_phone_no :
                                (($stu->g_phone_no_status == 1) ? $stu->g_phone : $stu->Phone));
                $check_arr_data['phone'] = $phoneno;
                $orgvaldet = OrganizationalStructureValues::find(array(
                            "columns" => 'group_concat(name) as subjnames',
                            " id  IN (" . implode(' , ', $params['subjaggregate']) . ")"
                ));
                $aggregate_keys = count($orgvaldet) > 0 ? ($orgvaldet[0]->subjnames != '' ? (explode(',', $orgvaldet[0]->subjnames)) : '') : '';
                $check_arr_data['subject'] = implode('>>', $aggregate_keys);
                $assignmts = AssignmentsMaster::findFirstById($params['assignment_id']);
                $assMarks = AssignmentMarks::findFirst('assignment_id = ' . $params['assignment_id']
                                . ' and student_id = ' . $stu->student_info_id);
                $check_arr_data['message'][] = $assignmts->topic . '-' . ($assMarks->marks ? ($assMarks->marks . '/' . $assMarks->outof) : 'not entered');

                $check_arr_msg['smstxt'] = "Your child " . $check_arr_data['Name']
                        . " assignment marks in "
                        . $check_arr_data['subject'] . " ";

                $check_arr_msg['smstxt'] .= implode(' ', $check_arr_data['message']);

                $stu_data[] = $stu->Student_Name;
                $stu_data[] = $check_arr_data['phone'];
                $stu_data[] = $check_arr_msg['smstxt'];
                $stu_data[] = date('d-m-Y');
//                $return = $this->sendSMSByGateway($check_arr_data['phone'], $check_arr_msg['smstxt']);

                $return = ControllerBase::sendSMSByGateway($check_arr_data['phone'], $check_arr_msg['smstxt']);
                $sentsmsstatus = new SentSmsResults();
                $sentsmsstatus->user_id = $stu->student_info_id;
                $sentsmsstatus->message = $template;
                $sentsmsstatus->date = time();
                if ($return == 0) {
                    $stu_data[] = 'Failed';
                    $sentsmsstatus->message_status = 'Failed';
                    $failed_sms++;
                } else {
                    $stu_data[] = 'Success';
                    $sentsmsstatus->message_status = 'Success';
                    //divide the message length by 160, 1 msg = 160 characters
                    $total_msg = round(strlen($check_arr_msg['smstxt']) / 160);
                    $no_of_msgs = $no_of_msgs + $total_msg;
                }
                if (preg_match('/^[0-9]*$/', $return)) {
                    $stu_data[] = $return;
                    $sentsmsstatus->responseid = $return;
                } else {
                    $stu_data[] = '';
                    $sentsmsstatus->responseid = '';
                }

                $fp = fopen(DOWNLOAD_DIR . $filename, 'a');
                fputcsv($fp, $stu_data, $delimiter);
                $sentsmsstatus->save();
            }
            fclose($fp);
        }

        $newtemplate = SmsTemplates::findFirst('temp_content = "' . $template . '"') ?
                SmsTemplates::findFirst('temp_content = "' . $template . '"') :
                new SmsTemplates();
        $newtemplate->temp_content = $template;
        $newtemplate->temp_status = 'Approved';
        $newtemplate->tags = 'template';
        $newtemplate->is_active = 1;
        $newtemplate->save();

        $sentsms = new SentSms();
        $sentsms->template_id = $newtemplate->temp_id;
        $sentsms->time = time();
        $sentsms->number_of_recipients = count($stumapdet);
        $sentsms->number_of_failures = $failed_sms;
        $sentsms->number_of_messages = $no_of_msgs;
        $sentsms->report_file = $filename;
        $sentsms->save();


        if ($no_of_msgs > 0) {
            $message['type'] = 'success';
            $message['message'] = '<div class="alert alert-block alert-success fade in">Message sent successfully!</div>'; // $appln->status . ' Successfully!';
            print_r(json_encode($message));
            exit;
        } else {
            $message['type'] = 'success';
            $message['message'] = '<div class="alert alert-block alert-success fade in">Message sending failed..!</div>'; // $appln->status . ' Successfully!';
            print_r(json_encode($message));
            exit;
        }
    }

    public function loadAnalysisSecAction() {

        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $clsid = $subjrr = array();
//        $currentacdyr = ControllerBase::get_current_academic_year();
//        $classrooms = ClassroomMaster::find(" FIND_IN_SET( " . $currentacdyr->id . ", REPLACE( aggregated_nodes_id,  '-',  ',' ) ) >0");
//        foreach ($classrooms as $cvalue) {
//            $clsid[] = $cvalue->id;
//        }
//        $subjectsid = GroupSubjectsTeachers::find('classroom_master_id IN (' . implode(',', $clsid) . ')');
//        
//        //$subjectsid = GroupSubjectsTeachers::find(" FIND_IN_SET( " . $currentacdyr->id . ", REPLACE( aggregated_nodes_id,  '-',  ',' ) ) >0");
//        foreach ($subjectsid as $svalue) {
//            $subjrr[] = $svalue->subject_id;
//        }
//
//        $this->view->subjects = OrganizationalStructureValues::find('id IN (' . implode(',', $subjrr) . ')');
    }

    public function loadClassroomsAction() {

        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);

        if ($this->request->isPost()) {
            $subjectid = $this->request->getPost('subjectid');
            $subjects = GroupSubjectsTeachers::find('subject_id = ' . $subjectid);
            foreach ($subjects as $svalue) {
                $clsid[] = $svalue->classroom_master_id;
            }
            $this->view->classrooms = $classrooms = ClassroomMaster::find('id IN (' . implode(',', array_unique($clsid)) . ')');
        }
    }

    public function loadComparisionAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $mainexam = array();
        if ($this->request->isPost()) {
            foreach ($this->request->getPost() as $key => $value) {
                $params[$key] = $value;
            }
            $subject = $params['subject'];
            $compareclassroom = explode(',', $params['compareclassroom'][0]);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                foreach ($submaster as $ssvalue) {
                    $subjrr[] = $ssvalue->id;
                }
                $mainexamMarks = MainexamMarks::find('grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subject_id = ' . $subject);
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $name = array();
                $name[] = $nodename->name;
//                foreach ($cname as $val) {
//                    $v = explode('>>', $val);
//                    array_shift($v);
//                    $name[] = implode(' >> ', $v);
//                }
                foreach ($mainexamMarks as $mainexMark) {
                    $exmname = Mainexam::findFirstById($mainexMark->mainexam_id);
                    $mainexam[$mainexMark->mainexam_id] = $exmname->exam_name;
                    $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                    $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);

                    if (($obtainedoutOf > 0)) {
                        $overalstutotalmarks = ($obtainedmark / $obtainedoutOf * 100);
                    }
                    $data['type'] = 'column';
                    $data['cval'] = $cvalue;
                    $data['name'] = implode(' ', $name);
                    $datadat[$mainexMark->mainexam_id] = $overalstutotalmarks;

                    $piesum[$mainexMark->mainexam_id] += $overalstutotalmarks;

                    $piedats[$cvalue]['name'] = implode(' ', $name);
                    $piedats[$cvalue]['y'] += $overalstutotalmarks;
                }

                $data['data'] = '';
                $data['data'] = array_values($datadat);
                $maindata[] = $data;
            }

            $pdata['type'] = 'pie';
            $pdata['name'] = 'Total Marks';
            $pdata['data'] = array_values($piedats);
            $maindata[] = $pdata;
            foreach ($maindata as $avalue) {
                $splineavg[$avalue['cval']] = round(array_sum($avalue['data']) / count($avalue['data']), 2);
            }
            $sdata['type'] = 'spline';
            $sdata['name'] = 'Average';
            $sdata['data'] = array_values($splineavg);
            $maindata[] = $sdata;
            $items = json_encode($maindata);
//            print_r($items);
//            exit;
            $this->view->items = $items;
            $this->view->mainexam = json_encode(array_values($mainexam));
        }
    }

    public function loadSubjectsAnalAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();

        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {

                $params[$key] = $value;
            }
        }

        $subjpids = ControllerBase::getAlSubjChildNodes($params['aggregateids']);
        $subjects = ControllerBase::getAllPossibleSubjectsold($subjpids);
        $this->view->subjects = $subjects;
    }

    public function loadSubtreeClsAnalAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost() && $this->request->getPost('orgvalueid') != '') {
            $this->view->orgvalueid = $orgvalueid = $this->request->getPost('orgvalueid');
            $this->view->org_value = $org_value = OrganizationalStructureValues::find('parent_id = ' . $orgvalueid
                            . ' GROUP BY  org_master_id ');
        }
    }

    public function loadComparisonChartAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
    }

    public function loadSubtreeCmprsnChartAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost() && $this->request->getPost('orgvalueid') != '') {
            $this->view->orgvalueid = $orgvalueid = $this->request->getPost('orgvalueid');
            $this->view->org_value = $org_value = OrganizationalStructureValues::find('parent_id = ' . $orgvalueid
                            . ' GROUP BY  org_master_id ');
        }
    }

    public function loadSubjectsCmprsnChartAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();

        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {

                $params[$key] = $value;
            }
        }
        $subjpids = ControllerBase::getAlSubjChildNodes($params['aggregateids']);
        $subjects = ControllerBase::getAllPossibleSubjectsold($subjpids);
        $this->view->subjects = $subjects;
    }

    public function loadClassroomsCmprsnChartAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();
        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {

                $params[$key] = $value;
            }
        }
        $narr = preg_filter('/^([\d,])*/', '(find_in_set("$0",  REPLACE(aggregated_nodes_id ,  "-",  "," ))>0)'
                , $params['aggregateids']);
        $nquery = '(' . implode(' and ', $narr) . ') ';

        $this->view->classrooms = $classrooms = ClassroomMaster::find($nquery);
    }

    public function loadStudentsCmprsnChartAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();
        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {

                $params[$key] = $value;
            }
        }

        $this->view->nodes = implode(',', $params['aggregateids']);
    }

    public function getCmprsnChartListAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $mainexam = $data = array();
        if ($this->request->isPost()) {
            foreach ($this->request->getPost() as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv[0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                    $params['subjaggregate'][] = $value;
                } else {
                    $params[$key] = $value;
                }
            }
            //  $subjpids = ControllerBase::getAlSubjChildNodes($params['aggregateids']);
            $i = 0;
            $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
            if ($params['student_list']) {
                $students = explode(',', $params['student_list']);
                foreach ($students as $stud) {
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $res = ControllerBase::buildExamQuery(implode(',', $params['aggregateids']));
                    $mainexamdet = Mainexam ::find(implode(' or ', $res));
                    $seriesval = array();
                    $overallexmcut = $studentexamcnt = 0;
                    foreach ($mainexamdet as $mainex) {
                        $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                        $subj_Ids = array();
                        $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                        $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                        $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                        foreach ($subjectsid as $svalue) {
                            $subj_Ids[] = $svalue->subject_id;
                        }
                        $subj_Ids = array_unique($subj_Ids);
                        $overalclsout = $studentpercentforchart = 0;
                        foreach ($subj_Ids as $sub) {
                            $suject = array();
                            $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                            //  $suject = end($subjagg);
                            $suject = $this->find_childtreevaljson($sub);
                            $cnt = 0;
                            // $cnt = count(explode(',', $suject));
                            $cnt = count($suject);
                            $subject = explode(',', $suject);
                            $overalstuout = $overalstutotalmarks = 0;
                            $mainexamMarks = MainexamMarks::find('mainexam_id = ' . $mainex->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and subject_id IN ( ' . implode(',', $subjagg) . ')');
                            foreach ($mainexamMarks as $mainexMark) {
                                $exmname = Mainexam::findFirstById($mainexMark->mainexam_id);
                                $mainexam[$mainexMark->mainexam_id] = $exmname->exam_name;
                                $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                                $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                                if (($obtainedoutOf > 0)) {
                                    $studentpercentforchart += ($obtainedmark / $obtainedoutOf * 100);
                                }
                            }
                            $overalclsout += $cnt;
                        }
                        if ($overalclsout > 0) {
                            $studentexamcnt += round($studentpercentforchart / $overalclsout, 2);
                        }
                        $overallexmcut ++;
                    }

                    if ($overallexmcut > 0) {
                        $seriesval[] = round($studentexamcnt / $overallexmcut, 2);
                    }
                    if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                        $data['data'] = '';
                        $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                        $data['color'] = $colors[$i++];
                        $data['name'] = $stud_name;
                        $data['data'] = $seriesval;
                        $maindata[] = $data;
                    }
                }
            }
            if ($params['compareclassroom'][0]) {
                $compareclassroom = explode(',', $params['compareclassroom'][0]);
                foreach ($compareclassroom as $cvalue) {
                    $subjrr = array();
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                    foreach ($submaster as $ssvalue) {
                        $subjrr[] = $ssvalue->id;
                    }
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $students = $this->modelsManager->executeQuery($stuquery);
                    $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                    $subj_Ids = array();
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                    $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                    foreach ($subjectsid as $svalue) {
                        $subj_Ids[] = $svalue->subject_id;
                    }
                    $subj_Ids = array_unique($subj_Ids);
                    $name = array();
                    $name[] = $nodename->name;
//                    foreach ($cname as $val) {
//                        $v = explode('>>', $val);
//                        array_shift($v);
//                        $name[] = implode(' >> ', $v);
//                    }
                    $overallsubcnt = $percnt = 0;
                    $seriesva = array();
                    $series = 0;
                    $res = ControllerBase::buildExamQuery(implode(',', $params['aggregateids']));
                    $exam_arr = Mainexam ::find(implode(' or ', $res));
                    foreach ($exam_arr as $exm) {
                        $overalclsout = $studentpercentforchart = 0;
                        foreach ($subj_Ids as $sub) {
                            $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                            // $sject = end($subjagg);
                            $sject = $this->find_childtreevaljson($sub);
                            $cnt = 0;
                            // $cnt = count(explode(',', $sject));
                            $cnt = count($sject);
                            $sub_det = explode(',', $sject);
                            $mainexamMarks = MainexamMarks::find('mainexam_id =' . $exm->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subject_id IN ( ' . implode(',', $subjagg) . ')');
                            $overalstuout = $overalstutotalmarks = 0;
                            foreach ($mainexamMarks as $mainexMark) {
                                $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                                $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                                if (($obtainedoutOf > 0)) {
                                    $studentpercentforchart += ($obtainedmark / $obtainedoutOf * 100);
                                }
                            }
                            $overalclsout += $cnt;
                        }

                        if ($overalclsout > 0) {
                            $percnt += round($studentpercentforchart / $overalclsout, 2);
                        }

                        $overallsubcnt ++;
                    }
                    if ($overallsubcnt > 0) {
                        $series = round($percnt / $overallsubcnt, 2);
                        $seriesva[] = round($series / count($students), 2);
                    }

                    if ($params['type'] == 'bar' || $params['type'] == 'column' || $params['type'] == 'line') {
                        $data['data'] = '';
                        $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                        $data['color'] = $colors[$i++];
                        $data['name'] = $name;
                        $data['data'] = $seriesva;
                        $maindata[] = $data;
                    }
                    if ($params['type'] == 'pie'):
                        if ($seriesva[0] != 0) {
                            $maindata['type'] = 'pie';
                            $maindata['name'] = 'MainExam';
                            $data['color'] = $colors[$i++];
                            $data['name'] = $name;
                            $data['y'] = array_shift($seriesva);
                            $maindata['data'][] = $data;
                        }
                    endif;
                }
            }

            if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line'):
                $this->view->items = $maindata ? json_encode($maindata) : '';
            endif;
            $this->view->xaxis = 'MainExam';
            $this->view->node_id = implode(',', $params['aggregateids']);
            $this->view->type = $params['type'];
            $this->view->student = $params['student_list'] ? $params['student_list'] : '';
            $this->view->classroom = $params['compareclassroom'][0] ? $params['compareclassroom'][0] : '';
            $this->view->name = $name = ControllerBase::getNameForKeys(implode(',', $params['aggregateids']));
        }
    }

    public function getSubtreeSubjectCmprsnChartAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost() && $this->request->getPost('orgvalueid') != '') {
            $this->view->orgvalueid = $orgvalueid = $this->request->getPost('orgvalueid');
            $this->view->org_value = $org_value = OrganizationalStructureValues::find('parent_id = ' . $orgvalueid
                            . ' GROUP BY  org_master_id ');
        }
    }

    public function getCmprsnChartListNewAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $mainexam = array();
        if ($this->request->isPost()) {
            foreach ($this->request->getPost() as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv [0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                    $params['subjaggregate'][] = $value;
                } else {
                    $params[$key] = $value;
                }
            }
            $subject = end($params['subjaggregate']);
            if ($params['activity'] == 1) {
                if ($params['student_list']) {
                    $exam_arryval = array();
                    $res = ControllerBase::buildExamQuery(implode(',', $params['aggregateids']));
                    $mainexamdet = Mainexam ::find(implode(' or ', $res));
                    foreach ($mainexamdet as $mainex) {
                        $exam_arryval[] = $mainex->id;
                    }
                    $arryval = array_unique($exam_arryval);
                    $students = explode(',', $params['student_list']);
                    foreach ($students as $stud) {
                        $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                        foreach ($arryval as $val1) {
                            $overalstutotalmarks = 0;
                            $exmname = Mainexam::findFirstById($val1);
                            $mainexamMarks = MainexamMarks::find('student_id = ' . $stud . ' and subject_id = ' . $subject);
                            if (count($mainexamMarks) > 0) {
                                $name = '';
                                $exam_name = $exam_name_colr = array();
                                $freq = array();
                                foreach ($mainexamMarks as $mainexMark) {
                                    if ($val1 == $mainexMark->mainexam_id):
                                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                                        if (($obtainedoutOf)) {
                                            $overalstutotalmarks = ($obtainedmark / $obtainedoutOf * 100);
                                        }
                                    endif;
                                }
                            }
                            $datadat[$exmname->exam_name] = $overalstutotalmarks;
                            $exam_name[] = "'" . $exmname->exam_name . "'";
                            $name = $exam_name;
                            $yaxis['freq'] = $datadat;
                            $yaxis['State'] = $stud_name;
                        }
                        $arr[] = $yaxis;
                    }
                }
                if ($params['compareclassroom'][0]) {
                    $compareclassroom = explode(',', $params['compareclassroom'][0]);
                    foreach ($compareclassroom as $cvalue) {
                        $subjrr = array();
                        $nodename = ClassroomMaster::findFirstById($cvalue);
                        $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                        foreach ($submaster as $ssvalue) {
                            $subjrr[] = $ssvalue->id;
                        }
                        $mainexamMarks = MainexamMarks::find('grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subject_id = ' . $subject);
                        $exam_arr = array();
                        $res = ControllerBase::buildExamQuery(implode(',', $params['aggregateids']));
                        $mainexamdet = Mainexam ::find(implode(' or ', $res));
                        foreach ($mainexamdet as $mainex) {
                            $exam_arr[] = $mainex->id;
                        }
                        $arry = array_unique($exam_arr);
                        $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                        $namearr = array();
                        $name[] = $nodename->name;
//                        foreach ($cname as $val) {
//                            $v = explode('>>', $val);
//                            array_shift($v);
//                            $namearr[] = implode(' >> ', $v);
//                        }
                        $datadat = array();
                        foreach ($arry as $v1) {
                            $freq = array();
                            $overalstuout = $overalstutotalmarks = $studentpercentforchart = 0;
                            $exmname = Mainexam::findFirstById($v1);
                            if (count($mainexamMarks) > 0) {
                                foreach ($mainexamMarks as $mainexMark) {
                                    if ($v1 == $mainexMark->mainexam_id):
                                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                                        if (($obtainedoutOf > 0)) {
                                            $overalstutotalmarks += ($obtainedmark / $obtainedoutOf * 100);
                                            $overalstuout ++;
                                        }
                                    endif;
                                }
                                if ($overalstuout > 0) {
                                    $studentpercentforchart = round($overalstutotalmarks / $overalstuout, 2);
                                }
                            }
                            $data = $studentpercentforchart;
                            $datadat[$exmname->exam_name] = $data;
                            $exam_name[] = "'" . $exmname->exam_name . "'";
                            $name = $exam_name;
                            $yaxis['freq'] = $datadat;
                            $yaxis['State'] = $namearr;
                        }
                        $arr[] = $yaxis;
                    }
                }
            }
//              print_r($arr);
//              exit;
            if ($params['activity'] == 2) {
                if ($params['student_list']) {
                    $students = explode(',', $params['student_list']);
                    $subjpids = ControllerBase::getAlSubjChildNodes($params['aggregateids']);
                    $subjids = ControllerBase::getGrpSubjMasPossiblities($params['aggregateids']);
                    $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules = ' . $subject);
                    foreach ($students as $stud) {
                        $stud_name = StudentInfo::findFirstById($stud);
                        if (count($classtests) > 0) {
                            foreach ($classtests as $classtest) {
                                $datadat = array();
                                $overalstuout = $overalstutotalmarks = 0;
                                $clsTstMarks = ClassTestMarks::findFirst('class_test_id = ' . $classtest->class_test_id . ' and student_id = ' . $stud);
                                if ($clsTstMarks) {
                                    $obtainedmark = ($clsTstMarks->marks) ? $clsTstMarks->marks : 0;
                                    $obtainedOutOf = ($clsTstMarks->outof) ? $clsTstMarks->outof : 0;
                                    if (($obtainedOutOf) > 0) {
                                        $overalstutotalmarks = ($obtainedmark / $obtainedOutOf * 100);
                                    }
                                }
                                $datadat = $overalstutotalmarks;
                                $data[$classtest->class_test_name] = $datadat;
                                $name[] = "'" . $classtest->class_test_name . "'";
                            }
                        }
                        $yaxisval['freq'] = $data;
                        $yaxisval['State'] = $stud_name->Student_Name;
                        $arr[] = $yaxisval;
                    }
                }
                if ($params['compareclassroom'][0]) {
                    $compareclassroom = explode(',', $params['compareclassroom'][0]);
                    foreach ($compareclassroom as $cvalue) {
                        $subjrr = array();
                        $nodename = ClassroomMaster::findFirstById($cvalue);
                        $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                        foreach ($submaster as $ssvalue) {
                            $subjrr[] = $ssvalue->id;
                        }
                        $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subjct_modules = ' . $subject);
                        $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                        $namearr = array();
                        $name[] = $nodename->name;
//                        foreach ($cname as $val) {
//                            $v = explode('>>', $val);
//                            array_shift($v);
//                            $namearr[] = implode(' >> ', $v);
//                        }
                        $data = array();
                        if (count($classtests) > 0) {
                            foreach ($classtests as $classtest) {
                                $datadat = array();
                                $overalstuout = $overalstutotalmarks = $studentpercentforchart = 0;
                                $clsTstMarks = ClassTestMarks::find('class_test_id = ' . $classtest->class_test_id);
                                foreach ($clsTstMarks as $mark) {
                                    $obtainedmark = ($mark->marks) ? $mark->marks : 0;
                                    $obtainedOutOf = ($mark->outof) ? $mark->outof : 0;
                                    if (($obtainedOutOf) > 0) {
                                        $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                        $overalstuout ++;
                                    }
                                }
                                if ($overalstuout > 0) {
                                    $studentpercentforchart = round($overalstutotalmarks / $overalstuout, 2);
                                }
                                $datadat = $studentpercentforchart;
                                $data[$classtest->class_test_name] = $datadat;
                                $name[] = "'" . $classtest->class_test_name . "'";
                            }

                            $yaxisval['freq'] = $data;
                            $yaxisval['State'] = $namearr;
                            $arr[] = $yaxisval;
                        }
                    }
                }
            }
            if ($params['activity'] == 3) {
                if ($params['student_list']) {
                    $students = explode(',', $params['student_list']);
                    $subjpids = ControllerBase::getAlSubjChildNodes($params['aggregateids']);
                    $subjids = ControllerBase::getGrpSubjMasPossiblities($params['aggregateids']);
                    $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules = ' . $subject);
                    foreach ($students as $stud) {
                        $stud_name = StudentInfo::findFirstById($stud);
                        if (count($assignments) > 0) {
                            foreach ($assignments as $ass) {
                                $datadat = array();
                                $overalstuout = $overalstutotalmarks = 0;
                                $assMarks = AssignmentMarks::findFirst('assignment_id = ' . $ass->id . ' and student_id = ' . $stud);
                                if ($assMarks) {
                                    $obtainedmark = ($assMarks->marks) ? $assMarks->marks : 0;
                                    $obtainedOutOf = ($assMarks->outof) ? $assMarks->outof : 0;
                                    if (($obtainedOutOf) > 0) {
                                        $overalstutotalmarks = ($obtainedmark / $obtainedOutOf * 100);
                                    }
                                }
                                $datadat = $overalstutotalmarks;
                                $data[$ass->topic] = $datadat;
                                $name[] = "'" . $ass->topic . "'";
                            }
                        }
                        $yaxisval['freq'] = $data;
                        $yaxisval['State'] = $stud_name->Student_Name;
                        $arr[] = $yaxisval;
                    }
                }
                if ($params['compareclassroom'][0]) {
                    $compareclassroom = explode(',', $params['compareclassroom'][0]);
                    foreach ($compareclassroom as $cvalue) {
                        $subjrr = array();
                        $nodename = ClassroomMaster::findFirstById($cvalue);
                        $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                        foreach ($submaster as $ssvalue) {
                            $subjrr[] = $ssvalue->id;
                        }
                        $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subjct_modules = ' . $subject);
                        $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                        $namearr = array();
                        $name[] = $nodename->name;
//                        foreach ($cname as $val) {
//                            $v = explode('>>', $val);
//                            array_shift($v);
//                            $namearr[] = implode(' >> ', $v);
//                        }
                        $data = array();
                        if (count($assignments) > 0) {
                            foreach ($assignments as $assignment) {
                                $datadat = array();
                                $overalstuout = $overalstutotalmarks = $studentpercentforchart = 0;
                                $assgnMarks = AssignmentMarks::find('assignment_id = ' . $assignment->id);
                                if ($assgnMarks) {
                                    foreach ($assgnMarks as $mark) {
                                        $obtainedmark = ($mark->marks) ? $mark->marks : 0;
                                        $obtainedOutOf = ($mark->outof) ? $mark->outof : 0;
                                        if (($obtainedOutOf) > 0) {
                                            $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                            $overalstuout ++;
                                        }
                                    }
                                    if ($overalstuout > 0) {
                                        $studentpercentforchart = round($overalstutotalmarks / $overalstuout, 2);
                                    }
                                }
                                $datadat = $studentpercentforchart;
                                $data[$assignment->topic] = $datadat;
                                $name[] = "'" . $assignment->topic . "'";
                            }

                            $yaxisval['freq'] = $data;
                            $yaxisval['State'] = $namearr;
                            $arr[] = $yaxisval;
                        }
                    }
                }
            }

            $color = array('#807dba', '#e08214', '#41ab5d', '#98abc5', '#8a89a6', '#7b6888', '#6b486b', '#a05d56', '#7cb5ec', '#ff8c00');
            $i = 0;
            $uni_name = array_unique($name);
            foreach ($uni_name as $n1) {
                $color_val [] = $n1 . ':' . '"' . $color[$i++] . '"';
            }
            $this->view->name = array_unique($name);
            $this->view->colors = $color_val;
            $this->view->value = json_encode($arr);
        }
    }

    public function loadProgressChartAction() {
        $this->view->setRenderLevel(View::

                LEVEL_ACTION_VIEW);
    }

    public function loadStudentsProgressChartAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost() && $this->request->getPost('orgvalueid') != '') {
            $this->view->orgvalueid = $orgvalueid = $this->request->getPost('orgvalueid');
        }
    }

    public function getProgressChartListAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $mainexam = array();
        if ($this->request->isPost()) {
            foreach ($this->request->getPost() as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv [0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else {
                    $params[$key] = $value;
                }
            }

            $aggreid = array();
            $stud = StudentInfo::findFirstById($params['student_value']);
            $aggregate_key = StudentMapping::findFirst('student_info_id =' . $params['student_value'])->aggregate_key;
            $aggreid = explode(',', $aggregate_key);
            $maping_values = '';
            $sub[] = 'Name :' . $stud->Student_Name;
            if ($aggreid != '') {
                foreach ($aggreid as $stu_mapdet) {
                    $orgnztn_str_det = OrganizationalStructureValues::findFirstById($stu_mapdet);
                    $orgnztn_str_mas_det = OrganizationalStructureMaster::findFirstById($orgnztn_str_det->org_master_id);
                    $sub[] = $orgnztn_str_mas_det->name . ':' . $orgnztn_str_det->name;
                }
            }

            $subjpids = ControllerBase::getAlSubjChildNodes($aggreid);
            $subjids = ControllerBase::getGrpSubjMasPossiblitiesold($aggreid);
            $subjects = ControllerBase::getAllPossibleSubjectsold($subjpids);
            $subj_Ids = array();
            foreach ($subjects as $nodes) {
                $subj_Ids[] = $nodes->id;
            }
            $progress = array();
            $i = 0;
            foreach ($subj_Ids as $value) {
                $examQury = '';
                if (count($subjids) > 0):
                    $examQury[] = " grp_subject_teacher_id IN (" . implode(' , ', $subjids) . ")";
                endif;
                $subjagg = ControllerBase::getAllSubjectAndSubModules(array($value));
                if (count(subjagg) > 0):
                    $examQury[] = 'subject_id IN(' . implode(',', $subjagg) . ')';
                endif;
                $examQury[] = 'student_id = ' . $params['student_value'];
                $conditionvals = (count($examQury) > 0) ? implode(' and ', $examQury) : '';
                $mainexamval = MainexamMarks::find($conditionvals);
                if (count($mainexamval) > 0) {
                    foreach ($mainexamval as $examval) {
                        $progress[$i]['subject'] = OrganizationalStructureValues::findFirstById($examval->subject_id)->name;
                        $exm = Mainexam::findFirstById($examval->mainexam_id);
                        $progress[$i]['name'] = $exm->exam_name;
                        $progress[$i]['mark'] = (($examval->inherited_marks) ? $examval->inherited_marks : 0 ) + (($examval->marks) ? $examval->marks : 0);
                        $progress[$i]['outof'] = (($examval->inherited_outof ) ? $examval->inherited_outof : 0 ) + (($examval->outof) ? $examval->outof : 0);
                        $progress[$i]['date'] = date('Y-m-d H:i:s', $examval->createdon);
                        if (($progress[$i]['outof'] ) > 0) {
                            $overalstutotalmarks = ( $progress[$i]['mark'] / $progress[$i]['outof'] * 100);
                        }
                        $progress[$i]['percentage'] = $overalstutotalmarks;
                        $i++;
                    }
                }
            }


//            foreach ($subj_Ids as $value) {
//                $clststQury = '';
//                if (count($subjids) > 0):
//                    $clststQury[] = " grp_subject_teacher_id IN (" . implode(' , ', $subjids) . ")";
//                endif;
//                $subjagg = ControllerBase::getAllSubjectAndSubModules(array($value));
//                if (count(subjagg) > 0):
//                    $clststQury[] = 'subjct_modules IN(' . implode(',', $subjagg) . ')';
//                endif;
//                 $clststQury[] = 'student_id IN(' . implode(',', $subjagg) . ;
//                $conditionvals = (count($clststQury) > 0) ? implode(' and ', $clststQury) : '';
//                $clstest = ClassTest::find($conditionvals);
//                if (count($clstest) > 0) {
//                    foreach ($clstest as $class_tests_val) {
//                        $overalstutotalmarks = 0;
//                        $stumark = ClassTestMarks::findFirst('class_test_id = ' . $class_tests_val->class_test_id . ' and student_id =' . $params['student_value']);
//                        // if ($stumark) {
//                        $progress[$i]['subject'] = OrganizationalStructureValues::findFirstById($class_tests_val->subjct_modules)->name;
//                        $progress[$i]['name'] = $class_tests_val->class_test_name;
//                        $progress[$i]['mark'] = $stumark->marks ? $stumark->marks : 0;
//                        $progress[$i]['outof'] = $stumark->outof ? $stumark->outof : 0;
//                        $progress[$i]['date'] = date('Y-m-d H:i:s', $stumark->createdon);
//                        $obtainedmark = ($stumark->marks) ? $stumark->marks : 0;
//                        $obtainedOutOf = ($stumark->outof) ? $stumark->outof : 0;
//                        if (($obtainedOutOf) > 0) {
//                            $overalstutotalmarks = ($obtainedmark / $obtainedOutOf * 100);
//                        }
//                        $progress[$i]['percentage'] = $overalstutotalmarks;
//                        $i++;
//                        // }
//                    }
//                }
//            }
//            foreach ($subj_Ids as $value) {
//                $clststQury = '';
//                if (count($subjids) > 0):
//                    $clststQury[] = " grp_subject_teacher_id IN (" . implode(' , ', $subjids) . ")";
//                endif;
//                $subjagg = ControllerBase::getAllSubjectAndSubModules(array($value));
//                if (count(subjagg) > 0):
//                    $clststQury[] = 'subjct_modules IN(' . implode(',', $subjagg) . ')';
//                endif;
//                $conditionvals = (count($clststQury) > 0) ? implode(' and ', $clststQury) : '';
//                $assignment = AssignmentsMaster::find($conditionvals);
//                if (count($assignment) > 0) {
//                    foreach ($assignment as $assgn) {
//                        $overalstutotalmarks = 0;
//                        $assgnmark = AssignmentMarks::findFirst('assignment_id = ' . $assgn->id . ' and student_id =' . $params['student_value']);
//                        $progress[$i]['subject'] = OrganizationalStructureValues::findFirstById($class_tests_val->subjct_modules)->name;
//                        $progress[$i]['name'] = $assgn->topic;
//                        $progress[$i]['mark'] = $assgnmark->marks ? $assgnmark->marks : 0;
//                        $progress[$i]['outof'] = $assgnmark->outof ? $assgnmark->outof : 0;
//                        $progress[$i]['date'] = date('Y-m-d H:i:s', $assgnmark->createdon);
//                        $obtainedmark = ($assgnmark->marks) ? $assgnmark->marks : 0;
//                        $obtainedOutOf = ($assgnmark->outof) ? $assgnmark->outof : 0;
//                        if (($obtainedOutOf) > 0) {
//                            $overalstutotalmarks = ($obtainedmark / $obtainedOutOf * 100);
//                        }
//                        $progress[$i]['percentage'] = $overalstutotalmarks;
//                        $i++;
//// }
//                    }
//                }
//            } foreach ($subj_Ids as $value) {
//                $clststQury = '';
//                if (count($subjids) > 0):
//                    $clststQury[] = " grp_subject_teacher_id IN (" . implode(' , ', $subjids) . ")";
//                endif;
//                $subjagg = ControllerBase::getAllSubjectAndSubModules(array($value));
//                if (count(subjagg) > 0):
//                    $clststQury[] = 'subjct_modules IN(' . implode(',', $subjagg) . ')';
//                endif;
//                $conditionvals = (count($clststQury) > 0) ? implode(' and ', $clststQury) : '';
//                $assignment = AssignmentsMaster::find($conditionvals);
//                if (count($assignment) > 0) {
//                    foreach ($assignment as $assgn) {
//                        $overalstutotalmarks = 0;
//                        $assgnmark = AssignmentMarks::findFirst('assignment_id = ' . $assgn->id . ' and student_id =' . $params['student_value']);
//                        $progress[$i]['subject'] = OrganizationalStructureValues::findFirstById($class_tests_val->subjct_modules)->name;
//                        $progress[$i]['name'] = $assgn->topic;
//                        $progress[$i]['mark'] = $assgnmark->marks ? $assgnmark->marks : 0;
//                        $progress[$i]['outof'] = $assgnmark->outof ? $assgnmark->outof : 0;
//                        $progress[$i]['date'] = date('Y-m-d H:i:s', $assgnmark->createdon);
//                        $obtainedmark = ($assgnmark->marks) ? $assgnmark->marks : 0;
//                        $obtainedOutOf = ($assgnmark->outof) ? $assgnmark->outof : 0;
//                        if (($obtainedOutOf) > 0) {
//                            $overalstutotalmarks = ($obtainedmark / $obtainedOutOf * 100);
//                        }
//                        $progress[$i]['percentage'] = $overalstutotalmarks;
//                        $i++;
//// }
//                    }
//                }
//            }

            $ee = usort($progress, 'AcademicReportsController::date_compare');
            foreach ($progress as $val) {
                $chart['y'] = $val['percentage'];
                $chart['subject'] = $val['subject'];
                $chart['name'] = $val['name'];
                $chart['date'] = $val['date'];
                $chart['mark'] = $val['mark'];
                $chart['outof'] = $val['outof'];
                $arr[] = $chart;
            }

            $this->view->tooltipval = json_encode($arr);

            $this->view->student = implode(',', $sub);
        }
    }

    public function date_compare($b, $a) {
        $t2 = strtotime($a['datetime']);
        $t1 = strtotime($b['datetime']);

        return $t1 - $t2;
    }

    public function displayOverallCmprsnChartAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $i = 0;
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                $res = ControllerBase::buildExamQuery($params['node_id']);
                $mainexamdet = Mainexam ::find(implode(' or ', $res));
                $seriesval = array();
                foreach ($mainexamdet as $mainex) {
                    $percent = $overalclsout = 0;
                    $subj_Ids = array();
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                    foreach ($subjectsid as $svalue) {
                        $subj_Ids[] = $svalue->subject_id;
                    }
                    $subj_Ids = array_unique($subj_Ids);
                    foreach ($subj_Ids as $sub) {
                        $suject = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $suject = $this->find_childtreevaljson($sub);
                        //$suject = end($subjagg);
                        $cnt = 0;
                        //   $cnt = count(explode(',', $suject));
                        $cnt = count($suject);
                        $subject = explode(',', $suject);
                        $overalstuout = $overalstutotalmarks = 0;
                        $mainexamMarks = MainexamMarks::find('mainexam_id=' . $mainex->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and subject_id IN ( ' . implode(',', $subjagg) . ')');

                        foreach ($mainexamMarks as $mainexMark) {
                            $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                            $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                            if (($obtainedoutOf > 0)) {
                                $percent += ($obtainedmark / $obtainedoutOf * 100);
                            }
                        }
                        $overalclsout += $cnt;
                    }
                    if ($overalclsout > 0) {
                        $seriesval[] = round($percent / $overalclsout, 2);
                    }
                    $mainexam[] = '<tspan >' . $mainex->exam_name . '<span style="display:none;">?' . $mainex->id . '</span></tspan>';
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $stud_name;
                    $data['data'] = array_values($seriesval);
                    $maindata[] = $data;
                }
            }
        }
        if ($params['classroom']) {
            $min_val = array();
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $subjrr = array();
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $stucount = explode('-', $nodename->aggregated_nodes_id);
                $res = ControllerBase::buildStudentQuery(implode(',', $stucount));

                $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                        . 'stumap.subordinate_key,stumap.status'
                        . ' FROM StudentMapping stumap LEFT JOIN'
                        . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                        . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                $students = $this->modelsManager->executeQuery($stuquery);

                $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                foreach ($submaster as $ssvalue) {
                    $subjrr[] = $ssvalue->id;
                }
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $subj_Ids = array();
                $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
                $subj_Ids = array_unique($subj_Ids);

                $name = array();
                $name[] = $nodename->name;
//                foreach ($cname as $val) {
//                    $v = explode('>>', $val);
//                    array_shift($v);
//                    $name[] = implode(' >> ', $v);
//                }
                $res = ControllerBase::buildExamQuery($params['node_id']);
                $mainexamdet = Mainexam ::find(implode(' or ', $res));
                $seriesval = array();
                $series = 0;
                foreach ($mainexamdet as $mainex) {
                    $percent = $overalclsout = 0;
                    $stutot = $stuoutof = $stuactoutof = $status = array();
                    foreach ($subj_Ids as $sub) {
                        $suject = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $suject = $this->find_childtreevaljson($sub);
                        $cnt = 0;
                        $cnt = count($suject);
                        $subject = explode(',', $suject);
                        $overalstuout = $overalstutotalmarks = 0;
                        $mainexamMarks = MainexamMarks::find('mainexam_id=' . $mainex->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subject_id IN ( ' . implode(',', $subjagg) . ')');
                        foreach ($mainexamMarks as $mainexMark) {
                            $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                            $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                            if (($obtainedoutOf > 0)) {
                                $actualper = ($obtainedmark / $obtainedoutOf * 100);
                                $percent += $actualper;
                                $nmark = $obtainedmark / $obtainedoutOf;
                                $stuuniout = $stuoutof[$mainex->id][$cvalue][$mainexMark->student_id] ? $stuoutof[$mainex->id][$cvalue][$mainexMark->student_id] : 0;
                                $stutotmark = $stutot[$mainex->id][$cvalue][$mainexMark->student_id] ? $stutot[$mainex->id][$cvalue][$mainexMark->student_id] : 0;
                                $stutotout = $stuactoutof[$mainex->id][$cvalue][$mainexMark->student_id] ? $stuactoutof[$mainex->id][$cvalue][$mainexMark->student_id] : 0;
                                $stutot[$mainex->id][$cvalue][$mainexMark->student_id] = $stutotmark + $nmark;
                                $stuoutof[$mainex->id][$cvalue][$mainexMark->student_id] = $stuuniout + 1;
                                $stuactoutof[$mainex->id][$cvalue][$mainexMark->student_id] = $stutotout + $obtainedoutOf;
                                $stat = $status[$mainex->id][$cvalue][$mainexMark->student_id] == 'fail' ? 1 : 0;
                                if (!$stat)
                                    $status[$mainex->id][$cvalue][$mainexMark->student_id] = ($actualper >= 40 ) ? 'pass' : 'fail';
                            }
                        }
//                        if ($overalstuout > 0) {
//                            $percent += round($overalstutotalmarks / $cnt, 2);
//                        }
                        // $overalclsout +=$cnt;
                        $overalclsout += $cnt;
                    }
                    if (count($stuoutof) > 0) {
                        foreach ($stutot[$mainex->id][$cvalue] as $key => $stot) {
                            $min_val[$mainex->id][$cvalue][$key]['mark'] = ($stot * $stuactoutof[$mainex->id][$cvalue][$key]) / $stuoutof[$mainex->id][$cvalue][$key];
                            $min_val[$mainex->id][$cvalue][$key]['outof'] = $stuactoutof[$mainex->id][$cvalue][$key];
                            $min_val[$mainex->id][$cvalue][$key]['stuid'] = $key;
                            if ($status[$mainex->id][$cvalue][$key] == 'pass') {
                                $min_val[$mainex->id][$cvalue][$key]['pass'] = $status[$mainex->id][$cvalue][$key];
                            } else {
                                $min_val[$mainex->id][$cvalue][$key]['fail'] = $status[$mainex->id][$cvalue][$key];
                            }
                        }
                    }
                    if ($overalclsout > 0) {
                        $series = round($percent / $overalclsout, 2);
                        $seriesval[] = round($series / count($students), 2);
                    }
                    $mainexam[] = '<tspan >' . $mainex->exam_name . '<span style="display:none;">?' . $mainex->id . '</span></tspan>';
                    $compare = $min_val;
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $name;
                    $data['data'] = array_values($seriesval);
                    $maindata[] = $data;
                }
            }
        }

        $this->view->items = $maindata ? json_encode($maindata) : '';
        $arryval = array_values($mainexam);
        $this->view->mainexam = json_encode($arryval);
        $this->view->node_id = $params['node_id'];
        $this->view->type = $params['type'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
//       echo '<pre>'; print_r($compare);exit;
        $this->view->compare = $compare;
    }

    public function displaySubwiseCmprsnChartAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $subj_Ids = array();
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $i = 0;
        $min_val = array();
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $percent = array();
                $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                $aggregate_key = array();
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
                $uniq_subid = array_unique($subj_Ids);
                $seriesval = array();
                $subject_name = array();
                foreach ($uniq_subid as $sub) {
                    $suject = array();
                    $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                    $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                    $suject = $this->find_childtreevaljson($sub);
                    $cnt = 0;
                    $cnt = count($suject);
                    $subject = explode(',', $suject);
                    $overalstuout = $overalstutotalmarks = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and  subject_id IN ( ' . implode(',', $subjagg) . ')');
                    foreach ($mainexamMarks as $mainexMark) {
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $overalstutotalmarks += ($obtainedmark / $obtainedoutOf * 100);
                        }
                        $overalstuout ++;
                    }

                    if ($overalstuout > 0) {
                        $percent[] = round($overalstutotalmarks / $cnt, 2);
                    } else {
                        $percent[] = 0;
                    }
                    $subject_name[] = count($subjagg) > 1 ? '<tspan style="color:red;text-decoration: none;cursor:pointer;" >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '?yes</span></tspan>' :
                            ' <tspan style="color:red;text-decoration: none;cursor:pointer;" >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '</span></tspan>';
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $stud_name;
                    $data['data'] = array_values($percent);
                    $maindata[] = $data;
                }
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);

            foreach ($compareclassroom as $cvalue) {

                $nodename = ClassroomMaster::findFirstById($cvalue);
                $subjrr = array();
                $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                foreach ($submaster as $ssvalue) {
                    $subjrr[] = $ssvalue->id;
                }
                $stucount = explode('-', $nodename->aggregated_nodes_id);
                $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                        . 'stumap.subordinate_key,stumap.status'
                        . ' FROM StudentMapping stumap LEFT JOIN'
                        . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                        . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                $students = $this->modelsManager->executeQuery($stuquery);
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
                $name = array();
                $name[] = $nodename->name;
//                foreach ($cname as $val) {
//                    $v = explode('>>', $val);
//                    array_shift($v);
//                    $name[] = implode(' >> ', $v);
//                }
                $percent = array();
                $finalval = 0;
                $subject_name = array();
                $uniq_subid = array_unique($subj_Ids);
                $stutot = $stuoutof = $stuactoutof = $status = array();
                foreach ($uniq_subid as $sub) {
                    $suject = array();
                    $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                    $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                    $suject = $this->find_childtreevaljson($sub);
                    $cnt = 0;
                    $cnt = count($suject);
                    $subject = explode(',', $suject);
                    $overalstuout = $overalstutotalmarks = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subject_id IN ( ' . implode(',', $subjagg) . ')');

                    foreach ($mainexamMarks as $mainexMark) {
//                        $min_val[$sub][$cvalue][$mainexMark->student_id] += (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $actualper = ($obtainedmark / $obtainedoutOf * 100);
                            $overalstutotalmarks += $actualper;
                            $nmark = $obtainedmark / $obtainedoutOf;
                            $stuuniout = $stuoutof[$sub][$cvalue][$mainexMark->student_id] ? $stuoutof[$sub][$cvalue][$mainexMark->student_id] : 0;
                            $stutotmark = $stutot[$sub][$cvalue][$mainexMark->student_id] ? $stutot[$sub][$cvalue][$mainexMark->student_id] : 0;
                            $stutotout = $stuactoutof[$sub][$cvalue][$mainexMark->student_id] ? $stuactoutof[$sub][$cvalue][$mainexMark->student_id] : 0;
                            $stutot[$sub][$cvalue][$mainexMark->student_id] = $stutotmark + $nmark;
                            $stuoutof[$sub][$cvalue][$mainexMark->student_id] = $stuuniout + 1;
                            $stuactoutof[$sub][$cvalue][$mainexMark->student_id] = $stutotout + $obtainedoutOf;
                            $stat = $status[$sub][$cvalue][$mainexMark->student_id] == 'fail' ? 1 : 0;
                            if (!$stat)
                                $status[$sub][$cvalue][$mainexMark->student_id] = ($actualper >= 40 ) ? 'pass' : 'fail';
                        }
                        $overalstuout ++;
                    }

                    if (count($stuoutof) > 0) {
                        foreach ($stutot[$sub][$cvalue] as $key => $stot) {
                            $min_val[$sub][$cvalue][$key]['mark'] = ($stot * $stuactoutof[$sub][$cvalue][$key]) / $stuoutof[$sub][$cvalue][$key];
                            $min_val[$sub][$cvalue][$key]['outof'] = $stuactoutof[$sub][$cvalue][$key];
                            $min_val[$sub][$cvalue][$key]['stuid'] = $key;
                            if ($status[$sub][$cvalue][$key] == 'pass') {
                                $min_val[$sub][$cvalue][$key]['pass'] = $status[$sub][$cvalue][$key];
                            } else {
                                $min_val[$sub][$cvalue][$key]['fail'] = $status[$sub][$cvalue][$key];
                            }
                        }
                    }
                    if ($overalstuout > 0) {
                        $finalval = round($overalstutotalmarks / $cnt, 2);
                        $percent[] = round($finalval / count($students), 2);
                    } else {
                        $percent[] = 0;
                    }

                    $subject_name[] = count($subjagg) > 1 ? '<tspan style="color:red;text-decoration: none;cursor:pointer;" >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '?yes</span></tspan>' :
                            '<tspan style="color:red;text-decoration: none;cursor:pointer;" >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '</span></tspan>';
                    $compare = $min_val;
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $name;
                    $data['data'] = array_values($percent);
                    $maindata[] = $data;
                }
            }
        }

        $manex = Mainexam ::findFirst('id=' . $params['exam_id']);
        $this->view->items = $maindata ? json_encode($maindata) : '';
        $this->view->exam_name = $manex->exam_name;
        $arryval = array_values($subject_name);
        $this->view->mainexam = json_encode($arryval);
        $this->view->node_id = $params['node_id'];
        $this->view->type = $params['type'];
        $this->view->exam_id = $params['exam_id'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
        $this->view->compare = $compare;
    }

    public function displaySubjctmoduleChartAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $i = 0;
        $this->view->sub = $sub = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id']);
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        if ($params['student']) {
            $students = explode(',', $params['student']);
            $subject_name = array();
            foreach ($students as $stud) {
                $overalstutotalmarks = array();
                $suject = array();
                $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subjagg = ControllerBase::getAllSubjectAndSubModules(array($params['subject_id']));
                $sub_first = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id']);
                $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and  subject_id IN ( ' . implode(',', $subjagg) . ') ORDER BY subject_id ');
                foreach ($mainexamMarks as $mainexMark) {
                    $sub_name = ControllerBase::getNameForKeys($mainexMark->subject_id);
                    $a = explode($sub_first->name . '>>', $sub_name[0]);
                    $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                    $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                    if (($obtainedoutOf > 0)) {
                        $overalstutotalmarks[] = ($obtainedmark / $obtainedoutOf * 100);
                    }
                    $subject_name[] = '<tspan style="color:red;text-decoration: none;cursor:pointer;" >' . end($a) . '<span style="display:none;">?' . $mainexMark->subject_id . '</span></tspan>';
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $colorval = round($stud % 10);
                    $data['color'] = $colors[$colorval];
                    $data['name'] = $stud_name;
                    $data['data'] = array_values($overalstutotalmarks);
                    $maindata[] = $data;
                }
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $subjrr = array();
                $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                foreach ($submaster as $ssvalue) {
                    $subjrr[] = $ssvalue->id;
                }
                $stucount = explode('-', $nodename->aggregated_nodes_id);
                $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                        . 'stumap.subordinate_key,stumap.status'
                        . ' FROM StudentMapping stumap LEFT JOIN'
                        . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                        . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                $students = $this->modelsManager->executeQuery($stuquery);
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $name = array();
                $name[] = $nodename->name;
                $suject = array();
                $subjagg = ControllerBase::getAllSubjectAndSubModules(array($params['subject_id']));
                $sub_first = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                $suject = $this->find_childtreevaljson($params['subject_id']);
                $cnt = 0;
                $cnt = count($suject);
                $subject = explode(',', $suject);
                $percent = array();
                $subject_name = array();
                $stutot = $stuoutof = $stuactoutof = $status = array();
                foreach ($suject as $arr) {
                    $overalstuout = $overalstutotalmarks = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subject_id = ' . $arr . 'ORDER BY subject_id ');

                    $sub_name = ControllerBase::getNameForKeys($arr);
                    $a = explode($sub_first->name . '>>', $sub_name[0]);
                    foreach ($mainexamMarks as $mainexMark) {
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $actualper = ($obtainedmark / $obtainedoutOf * 100);
//                            $overalstutotalmarks += $actualper;
                            $nmark = $obtainedmark / $obtainedoutOf;
                            $stuuniout = $stuoutof[$arr][$cvalue][$mainexMark->student_id] ? $stuoutof[$arr][$cvalue][$mainexMark->student_id] : 0;
                            $stutotmark = $stutot[$arr][$cvalue][$mainexMark->student_id] ? $stutot[$arr][$cvalue][$mainexMark->student_id] : 0;
                            $stutotout = $stuactoutof[$arr][$cvalue][$mainexMark->student_id] ? $stuactoutof[$arr][$cvalue][$mainexMark->student_id] : 0;
                            $stutot[$arr][$cvalue][$mainexMark->student_id] = $stutotmark + $nmark;
                            $stuoutof[$arr][$cvalue][$mainexMark->student_id] = $stuuniout + 1;
                            $stuactoutof[$arr][$cvalue][$mainexMark->student_id] = $stutotout + $obtainedoutOf;
                            $stat = $status[$arr][$cvalue][$mainexMark->student_id] == 'fail' ? 1 : 0;
                            if (!$stat)
                                $status[$arr][$cvalue][$mainexMark->student_id] = ($actualper >= 40 ) ? 'pass' : 'fail';
                        }
                        if (($obtainedoutOf > 0)) {
                            $overalstutotalmarks += ($obtainedmark / $obtainedoutOf * 100);
                        }
                        $overalstuout ++;
                    }
                    if (count($stuoutof) > 0) {
                        foreach ($stutot[$arr][$cvalue] as $key => $stot) {
                            $min_val[$arr][$cvalue][$key]['mark'] = ($stot * $stuactoutof[$arr][$cvalue][$key]) / $stuoutof[$arr][$cvalue][$key];
                            $min_val[$arr][$cvalue][$key]['outof'] = $stuactoutof[$arr][$cvalue][$key];
                            $min_val[$arr][$cvalue][$key]['stuid'] = $key;
                            if ($status[$arr][$cvalue][$key] == 'pass') {
                                $min_val[$arr][$cvalue][$key]['pass'] = $status[$arr][$cvalue][$key];
                            } else {
                                $min_val[$arr][$cvalue][$key]['fail'] = $status[$arr][$cvalue][$key];
                            }
                        }
                    }

                    if ($overalstuout > 0) {
                        $percent[] = round($overalstutotalmarks / count($students), 2);
                    } else {
                        $percent[] = 0;
                    }
                    $compare = $min_val;
                    $subject_name[] = '<tspan style="color:red;text-decoration: none;cursor:pointer;" >' . end($a) . '<span style="display:none;">?' . $arr . '</span></tspan>';
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $colorval = round($cvalue % 10);
                    $data['color'] = $colors[$colorval];
                    $data['name'] = $name;
                    $data['data'] = array_values($percent);
                    $maindata[] = $data;
                }
            }
        }
        $manex = Mainexam ::findFirst('id=' . $params['exam_id']);
        $this->view->exam_name = $manex->exam_name;
        $this->view->sub = $sub_first->name;
        $this->view->type = $params['type'];
        $this->view->items = $maindata ? json_encode($maindata) : '';
        $arryval = array_values($subject_name);
        $this->view->mainexam = json_encode($arryval);
        $this->view->compare = $compare;
        $this->view->type = $params['type'];
        $this->view->exam_id = $params['exam_id'];
        $this->view->node_id = $params['node_id'];
        $this->view->subject_id = $params['subject_id'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
    }

    public function submoduleMainExamPrintAction() {
        $this->view->setTemplateAfter('printTemplates');
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $i = 0;
        $this->view->sub = $sub = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id']);
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        if ($params['student']) {
            $students = explode(',', $params['student']);
            $subject_name = array();
            foreach ($students as $stud) {
                $overalstutotalmarks = array();
                $suject = array();
                $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subjagg = ControllerBase::getAllSubjectAndSubModules(array($params['subject_id']));
                $sub_first = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id']);
                $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and  subject_id IN ( ' . implode(',', $subjagg) . ') ORDER BY subject_id ');
                foreach ($mainexamMarks as $mainexMark) {
                    $sub_name = ControllerBase::getNameForKeys($mainexMark->subject_id);
                    $a = explode($sub_first->name . '>>', $sub_name[0]);
                    $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                    $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                    if (($obtainedoutOf > 0)) {
                        $overalstutotalmarks[] = ($obtainedmark / $obtainedoutOf * 100);
                    }
                    $subject_name[] = '<tspan style="color:red;text-decoration: none;cursor:pointer;" >' . end($a) . '<span style="display:none;">?' . $mainexMark->subject_id . '</span></tspan>';
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $colorval = round($stud % 10);
                    $data['color'] = $colors[$colorval];
                    $data['name'] = $stud_name;
                    $data['data'] = array_values($overalstutotalmarks);
                    $maindata[] = $data;
                }
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $subjrr = array();
                $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                foreach ($submaster as $ssvalue) {
                    $subjrr[] = $ssvalue->id;
                }
                $stucount = explode('-', $nodename->aggregated_nodes_id);
                $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                        . 'stumap.subordinate_key,stumap.status'
                        . ' FROM StudentMapping stumap LEFT JOIN'
                        . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                        . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                $students = $this->modelsManager->executeQuery($stuquery);
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $name = array();
                $name[] = $nodename->name;
                $suject = array();
                $subjagg = ControllerBase::getAllSubjectAndSubModules(array($params['subject_id']));
                $sub_first = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                $suject = $this->find_childtreevaljson($params['subject_id']);
                $cnt = 0;
                $cnt = count($suject);
                $subject = explode(',', $suject);
                $percent = array();
                $subject_name = array();
                $stutot = $stuoutof = $stuactoutof = $status = array();
                foreach ($suject as $arr) {
                    $overalstuout = $overalstutotalmarks = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subject_id = ' . $arr . 'ORDER BY subject_id ');
                    $sub_name = ControllerBase::getNameForKeys($arr);
                    $a = explode($sub_first->name . '>>', $sub_name[0]);
                    foreach ($mainexamMarks as $mainexMark) {
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $actualper = ($obtainedmark / $obtainedoutOf * 100);
                            $overalstutotalmarks += $actualper;
                            $nmark = $obtainedmark / $obtainedoutOf;
                            $stuuniout = $stuoutof[$cvalue][$arr][$mainexMark->student_id] ? $stuoutof[$cvalue][$arr][$mainexMark->student_id] : 0;
                            $stutotmark = $stutot[$cvalue][$arr][$mainexMark->student_id] ? $stutot[$cvalue][$arr][$mainexMark->student_id] : 0;
                            $stutotout = $stuactoutof[$cvalue][$arr][$mainexMark->student_id] ? $stuactoutof[$cvalue][$arr][$mainexMark->student_id] : 0;
                            $stutot[$cvalue][$arr][$mainexMark->student_id] = $stutotmark + $nmark;
                            $stuoutof[$cvalue][$arr][$mainexMark->student_id] = $stuuniout + 1;
                            $stuactoutof[$cvalue][$arr][$mainexMark->student_id] = $stutotout + $obtainedoutOf;
                            $stat = $status[$cvalue][$arr][$mainexMark->student_id] == 'fail' ? 1 : 0;
                            if (!$stat)
                                $status[$cvalue][$arr][$mainexMark->student_id] = ($actualper >= 40 ) ? 'pass' : 'fail';
                        }
                        if (($obtainedoutOf > 0)) {
                            $overalstutotalmarks += ($obtainedmark / $obtainedoutOf * 100);
                        }
                        $overalstuout ++;
                    }
                    if (count($stuoutof) > 0) {
                        foreach ($stutot[$cvalue][$arr] as $key => $stot) {
                            $min_val[$cvalue][$arr][$key]['mark'] = ($stot * $stuactoutof[$cvalue][$arr][$key]) / $stuoutof[$cvalue][$arr][$key];
                            $min_val[$cvalue][$arr][$key]['outof'] = $stuactoutof[$cvalue][$arr][$key];
                            $min_val[$cvalue][$arr][$key]['stuid'] = $key;
                            if ($status[$cvalue][$arr][$key] == 'pass') {
                                $min_val[$cvalue][$arr][$key]['pass'] = $status[$cvalue][$arr][$key];
                            } else {
                                $min_val[$cvalue][$arr][$key]['fail'] = $status[$cvalue][$arr][$key];
                            }
                        }
                    }
                    if ($overalstuout > 0) {
                        $percent[] = round($overalstutotalmarks / count($students), 2);
                    } else {
                        $percent[] = 0;
                    }
                    $compare = $min_val;
                    $subject_name[] = '<tspan style="color:red;text-decoration: none;cursor:pointer;" >' . end($a) . '<span style="display:none;">?' . $arr . '</span></tspan>';
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $colorval = round($cvalue % 10);
                    $data['color'] = $colors[$colorval];
                    $data['name'] = $name;
                    $data['data'] = array_values($percent);
                    $maindata[] = $data;
                }
            }
        }
        $manex = Mainexam ::findFirst('id=' . $params['exam_id']);
        $this->view->exam_name = $manex->exam_name;
        $this->view->sub = $sub_first->name;
        $this->view->compare = $compare;
    }

    public function getClasstestChartAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $mainexam = array();
        if ($this->request->isPost()) {
            foreach ($this->request->getPost() as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv[0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                    $params['subjaggregate'][] = $value;
                } else {
                    $params[$key] = $value;
                }
            }

            $i = 0;
            $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
            if ($params['student_list']) {
                $students = explode(',', $params['student_list']);
                foreach ($students as $stud) {
                    $seriesval = array();
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subj_Ids = array();
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                    foreach ($subjectsid as $svalue) {
                        $subj_Ids[] = $svalue->subject_id;
                    }
                    $subj_Ids = array_unique($subj_Ids);
                    $overalsubout = $studentpercentforchart = 0;
                    foreach ($subj_Ids as $sub) {
                        $suject = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $overalstuout = $overalstutotalmarks = 0;
                        $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                        if (count($classtests) > 0) {
                            foreach ($classtests as $classtest) {
                                $clsTstMarks = ClassTestMarks::findFirst('class_test_id = ' . $classtest->class_test_id . ' and student_id = ' . $stud);
                                $obtainedmark = ($clsTstMarks->marks) ? $clsTstMarks->marks : 0;
                                $obtainedOutOf = ($clsTstMarks->outof) ? $clsTstMarks->outof : 0;
                                if (($obtainedOutOf) > 0) {
                                    $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                }
                                $overalstuout ++;
                            }
                            if ($overalstuout > 0) {
                                $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            }

                            $overalsubout ++;
                        }
                    }

                    if ($overalsubout > 0) {
                        $seriesval[] = round($studentpercentforchart / $overalsubout, 2);
                    }

                    if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line'):
                        $data['data'] = '';
                        $data['type'] = $params['type'] == 'areaspline' ? "spline" : $params['type'];
                        $data['color'] = $colors[$i++];
                        $data[' pointStart'] = 'ClassTest';
                        $data['name'] = $stud_name;
                        $data['data'] = $seriesval;
                        $maindata[] = $data;
                    endif;
                }
            }

            if ($params['compareclassroom'][0]) {
                $compareclassroom = explode(',', $params['compareclassroom'][0]);
                foreach ($compareclassroom as $cvalue) {
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $students = $this->modelsManager->executeQuery($stuquery);
                    $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                    $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                    foreach ($subjectsid as $svalue) {
                        $subj_Ids[] = $svalue->subject_id;
                    }
                    $subj_Ids = array_unique($subj_Ids);
                    $name = array();
                    $name[] = $nodename->name;
//                    foreach ($cname as $val) {
//                        $v = explode('>>', $val);
//                        array_shift($v);
//                        $name[] = implode(' >> ', $v);
//                    }
                    $seriesval = 0;
                    $totalarray = array();
                    $overalsubout = $studentpercentforchart = 0;
                    foreach ($subj_Ids as $sub) {
                        $suject = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $overalstuout = $overalstutotalmarks = 0;
                        $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                        if (count($classtests)) {
                            foreach ($classtests as $classtest) {
                                $clsTstMarks = ClassTestMarks::find('class_test_id = ' . $classtest->class_test_id);
                                foreach ($clsTstMarks as $clsmark) {
                                    $obtainedmark = ($clsmark->marks) ? $clsmark->marks : 0;
                                    $obtainedOutOf = ($clsmark->outof) ? $clsmark->outof : 0;
                                    if (($obtainedOutOf) > 0) {
                                        $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                    }
                                }

                                $overalstuout ++;
                            }
                            if ($overalstuout > 0) {
                                $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            }
                            $overalsubout ++;
                        }
                    }
                    if ($overalsubout > 0) {
                        $seriesval += round($studentpercentforchart / $overalsubout, 2);
                        $totalarray[] = round($seriesval / count($students), 2);
                    }
                    if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line'):
                        $data['data'] = '';
                        $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                        $data['color'] = $colors[$i++];
                        $data['name'] = $name;
                        $data['data'] = $totalarray;
                        $maindata[] = $data;
                    endif;
                }
            }

            if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line'):
                $this->view->items = $maindata ? json_encode($maindata) : '';
            endif;
            $this->view->type = $params['type'];
            $this->view->xaxis = 'ClassTest';
            $this->view->node_id = implode(',', $params['aggregateids']);
            $this->view->student = $params['student_list'] ? $params['student_list'] : '';
            $this->view->classroom = $params['compareclassroom'][0] ? $params['compareclassroom'][0] : '';
            $this->view->name = $name = ControllerBase::getNameForKeys(implode(',', $params['aggregateids']));
        }
    }

    public function subwiseClasstestChartAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $i = 0;
        $subj_Ids = array();
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        if ($params['student']) {
            $students = explode(',', $params['student']);
            $arrdata = array();
            foreach ($students as $stud) {
                $seriesval = array();
                $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
                $subj_Ids = array_unique($subj_Ids);
                $studentpercentforchart = array();
                foreach ($subj_Ids as $sub) {
                    $suject = array();
                    $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                    $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                    $overalstuout = $overalstutotalmarks = 0;
                    $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                    if (count($classtests)) {
                        foreach ($classtests as $classtest) {
                            $clsTstMarks = ClassTestMarks::findFirst('class_test_id = ' . $classtest->class_test_id . ' and student_id = ' . $stud);
                            $obtainedmark = ($clsTstMarks->marks) ? $clsTstMarks->marks : 0;
                            $obtainedOutOf = ($clsTstMarks->outof) ? $clsTstMarks->outof : 0;
                            if (($obtainedOutOf) > 0) {
                                $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                            }
                            $overalstuout ++;
                        }


                        if ($overalstuout > 0) {
                            $studentpercentforchart[] = round($overalstutotalmarks / $overalstuout, 2);
                        } else {
                            $studentpercentforchart[] = 0;
                        }

                        $subject_name[] = count($subjagg) > 1 ? '<tspan >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '_yes' . '</span></tspan>' : '<tspan >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '_no' . '</span></tspan>';
                    }
                }

                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line'):
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $stud_name;
                    $data['data'] = $studentpercentforchart;
                    $maindata[] = $data;
                endif;
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $stucount = explode('-', $nodename->aggregated_nodes_id);
                $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                        . 'stumap.subordinate_key,stumap.status'
                        . ' FROM StudentMapping stumap LEFT JOIN'
                        . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                        . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                $students = $this->modelsManager->executeQuery($stuquery);
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
                $subj_Ids = array_unique($subj_Ids);
                $name = array();
                $name[] = $nodename->name;
//                foreach ($cname as $val) {
//                    $v = explode('>>', $val);
//                    array_shift($v);
//                    $name[] = implode(' >> ', $v);
//                }
                $seriesval = 0;
                $totalarray = array();

                foreach ($subj_Ids as $sub) {
                    $suject = array();
                    $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                    $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                    $overalstuout = $overalstutotalmarks = $studentpercentforchart = 0;
                    $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                    if (count($classtests)) {
                        foreach ($classtests as $classtest) {
                            $clsTstMarks = ClassTestMarks::find('class_test_id = ' . $classtest->class_test_id);
                            foreach ($clsTstMarks as $clsmark) {
                                $obtainedmark = ($clsmark->marks) ? $clsmark->marks : 0;
                                $obtainedOutOf = ($clsmark->outof) ? $clsmark->outof : 0;
                                if (($obtainedOutOf) > 0) {
                                    $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                }
                            }
                            $overalstuout ++;
                        }
                        if ($overalstuout > 0) {
                            $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            $totalarray[] = round($studentpercentforchart / count($students), 2);
                        }
                        $subject_name[] = count($subjagg) > 1 ? '<tspan >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '_yes' . '</span></tspan>' : '<tspan >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '_no' . '</span></tspan>';
                    }
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line'):
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $name;
                    $data['data'] = $totalarray;
                    $maindata[] = $data;
                endif;
            }
        }
        $this->view->items = $maindata ? json_encode($maindata) : '';
        $arryval = array_values($subject_name);
        $this->view->classtest = json_encode($arryval);
        $this->view->node_id = $params['node_id'];
        $this->view->type = $params['type'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
    }

    public function submoduleClasstestChartAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }

        $this->view->sub_header = $sub_name = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id']);
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $i = 0;
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $studentpercentforchart = array();
                $suject = array();
                $subjagg = $this->find_childtreevaljson($params['subject_id']);
                $subject = explode(',', $suject);
                foreach ($subjagg as $sub) {
                    $overalstuout = $overalstutotalmarks = 0;
                    $module_name = ControllerBase::getNameForKeys($sub);
                    $a = explode($sub_name->name . '>>', $module_name[0]);
                    $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules =' . $sub);
                    if (count($classtests)) {
                        foreach ($classtests as $classtest) {
                            $clsTstMarks = ClassTestMarks::findFirst('class_test_id = ' . $classtest->class_test_id . ' and student_id = ' . $stud);
                            $obtainedmark = ($clsTstMarks->marks) ? $clsTstMarks->marks : 0;
                            $obtainedOutOf = ($clsTstMarks->outof) ? $clsTstMarks->outof : 0;
                            if (($obtainedOutOf) > 0) {
                                $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                            }
                            $overalstuout ++;
                        }

                        if ($overalstuout > 0) {
                            $studentpercentforchart[] = round($overalstutotalmarks / $overalstuout, 2);
                        } else {
                            $studentpercentforchart[] = 0;
                        }

                        $subject_name[] = '<tspan >' . end($a) . '<span style="display:none;">?' . $sub . '</span></tspan>';
                    }
                }

                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line'):
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $stud_name;
                    $data['data'] = $studentpercentforchart;
                    $maindata[] = $data;
                endif;
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $stucount = explode('-', $nodename->aggregated_nodes_id);
                $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                        . 'stumap.subordinate_key,stumap.status'
                        . ' FROM StudentMapping stumap LEFT JOIN'
                        . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                        . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                $students = $this->modelsManager->executeQuery($stuquery);
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $name = array();
                $name[] = $nodename->name;
//                foreach ($cname as $val) {
//                    $v = explode('>>', $val);
//                    array_shift($v);
//                    $name[] = implode(' >> ', $v);
//                }
                $totalarray = array();
                $studentpercentforchart = 0;
                $suject = array();
                $subjagg = $this->find_childtreevaljson($params['subject_id']);
                foreach ($subjagg as $sub) {
                    $module_name = ControllerBase::getNameForKeys($sub);
                    $a = explode($sub_name->name . '>>', $module_name[0]);
                    $overalstuout = $overalstutotalmarks = 0;
                    $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules =' . $sub);
                    if (count($classtests)) {
                        foreach ($classtests as $classtest) {
                            $clsTstMarks = ClassTestMarks::find('class_test_id = ' . $classtest->class_test_id);
                            foreach ($clsTstMarks as $clsmark) {
                                $obtainedmark = ($clsmark->marks) ? $clsmark->marks : 0;
                                $obtainedOutOf = ($clsmark->outof) ? $clsmark->outof : 0;
                                if (($obtainedOutOf) > 0) {
                                    $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                }
                            }
                            $overalstuout ++;
                        }

                        if ($overalstuout > 0) {
                            $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            $totalarray[] = round($studentpercentforchart / count($students), 2);
                        }
                        $subject_name[] = '<tspan >' . end($a) . '<span style="display:none;">?' . $sub . '</span></tspan>';
                    }
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line'):
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $name;
                    $data['data'] = $totalarray;
                    $maindata[] = $data;
                endif;
            }
        }
        $this->view->items = $maindata ? json_encode($maindata) : '';
        $arryval = array_values($subject_name);
        $this->view->classtest = json_encode($arryval);
        $this->view->node_id = $params['node_id'];
        $this->view->type = $params['type'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
    }

    public function find_childtreevaljson($anode, $main = array()) {
        $exist = OrganizationalStructureValues::find('parent_id =' . $anode);
        if (count($exist) > 0) {
            foreach ($exist as $chl) {
                $main = $this->find_childtreevaljson($chl->id, $main);
            }
        } else {
            $main[] = OrganizationalStructureValues::findFirst('id =' . $anode)->id;
        }
        return $main;
    }

    public function individualClasstestChartAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        if ($params['sub_main_id']) {
            $module_name = ControllerBase::getNameForKeys($params['subject_id']);
            $sub_name = OrganizationalStructureValues::findFirst('id = ' . $params['sub_main_id']);
            $val = explode($sub_name->name . '>>', $module_name[0]);
            $this->view->sub_header = $sub_name->name . '>>' . $val[1];
        } else {
            $sub_name = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id']);
            $this->view->sub_header = $sub_name->name;
        }
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $i = 0;
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $overalstutotalmarks = array();
                $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules =' . $params['subject_id']);
                foreach ($classtests as $classtest) {
                    $clsTstMarks = ClassTestMarks::findFirst('class_test_id = ' . $classtest->class_test_id . ' and student_id = ' . $stud);
                    $obtainedmark = ($clsTstMarks->marks) ? $clsTstMarks->marks : 0;
                    $obtainedOutOf = ($clsTstMarks->outof) ? $clsTstMarks->outof : 0;
                    if (($obtainedOutOf) > 0) {
                        $overalstutotalmarks[] = ($obtainedmark / $obtainedOutOf * 100);
                    } else {
                        $overalstutotalmarks[] = 0;
                    }
                    $test_name[] = $classtest->class_test_name;
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line'):
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $stud_name;
                    $data['data'] = $overalstutotalmarks;
                    $maindata[] = $data;
                endif;
            }
        }

        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $stucount = explode('-', $nodename->aggregated_nodes_id);
                $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                        . 'stumap.subordinate_key,stumap.status'
                        . ' FROM StudentMapping stumap LEFT JOIN'
                        . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                        . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                $students = $this->modelsManager->executeQuery($stuquery);
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $name = array();
                $name[] = $nodename->name;
//                foreach ($cname as $val) {
//                    $v = explode('>>', $val);
//                    array_shift($v);
//                    $name[] = implode(' >> ', $v);
//                }

                $totalarray = array();
                $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules =' . $params['subject_id']);
                foreach ($classtests as $classtest) {
                    $overalstuout = $overalstutotalmarks = $studentpercentforchart = 0;
                    $clsTstMarks = ClassTestMarks::find('class_test_id = ' . $classtest->class_test_id);
                    foreach ($clsTstMarks as $clsmark) {
                        $obtainedmark = ($clsmark->marks) ? $clsmark->marks : 0;
                        $obtainedOutOf = ($clsmark->outof) ? $clsmark->outof : 0;
                        if (($obtainedOutOf) > 0) {
                            $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                        }
                        $overalstuout ++;
                    }
                    if ($overalstuout > 0) {
                        $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                        $totalarray[] = round($studentpercentforchart / count($students), 2);
                    } else {
                        $totalarray[] = 0;
                    }
                    $test_name[] = $classtest->class_test_name;
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line'):
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $name;
                    $data['data'] = $totalarray;
                    $maindata[] = $data;
                endif;
            }
        }
        $this->view->items = $maindata ? json_encode($maindata) : '';
        $arryval = array_values($test_name);
        $this->view->classtest = json_encode($arryval);
    }

    public function getAssignmentChartAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $mainexam = array();
        if ($this->request->isPost()) {
            foreach ($this->request->getPost() as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv[0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                    $params['subjaggregate'][] = $value;
                } else {
                    $params[$key] = $value;
                }
            }
            $i = 0;
            $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
            if ($params['student_list']) {
                $students = explode(',', $params['student_list']);
                foreach ($students as $stud) {
                    $seriesval = array();
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subj_Ids = array();
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                    foreach ($subjectsid as $svalue) {
                        $subj_Ids[] = $svalue->subject_id;
                    }
                    $subj_Ids = array_unique($subj_Ids);
                    $overalsubout = $studentpercentforchart = 0;
                    foreach ($subj_Ids as $sub) {
                        $suject = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $overalstuout = $overalstutotalmarks = 0;
                        $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                        if (count($assignments)) {
                            foreach ($assignments as $assign) {
                                $assignMarks = AssignmentMarks::findFirst('assignment_id = ' . $assign->id . ' and student_id = ' . $stud);
                                $obtainedmark = ($assignMarks->marks) ? $assignMarks->marks : 0;
                                $obtainedOutOf = ($assignMarks->outof) ? $assignMarks->outof : 0;
                                if (($obtainedOutOf) > 0) {
                                    $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                }
                                $overalstuout ++;
                            }
                            if ($overalstuout > 0) {
                                $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            }
                            $overalsubout ++;
                        }
                    }

                    if ($overalsubout > 0) {
                        $seriesval[] = round($studentpercentforchart / $overalsubout, 2);
                    }
                    if ($params['type'] == 'bar' || $params['type'] == 'column' || $params['type'] == 'line'):
                        $data['data'] = '';
                        $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                        $data['color'] = $colors[$i++];
                        $data['name'] = $stud_name;
                        $data['data'] = $seriesval;
                        $maindata[] = $data;
                    endif;
                }
            }

            if ($params['compareclassroom'][0]) {
                $compareclassroom = explode(',', $params['compareclassroom'][0]);
                foreach ($compareclassroom as $cvalue) {
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $students = $this->modelsManager->executeQuery($stuquery);
                    $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                    $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                    foreach ($subjectsid as $svalue) {
                        $subj_Ids[] = $svalue->subject_id;
                    }
                    $subj_Ids = array_unique($subj_Ids);
                    $name = array();
                    $name[] = $nodename->name;

//                    foreach ($cname as $val) {
//                        $v = explode('>>', $val);
//                        array_shift($v);
//                        $name[] = implode(' >> ', $v);
//                    }
                    $seriesval = 0;
                    $totalarray = array();
                    $overalsubout = $studentpercentforchart = 0;
                    foreach ($subj_Ids as $sub) {
                        $suject = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $overalstuout = $overalstutotalmarks = 0;
                        $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                        if (count($assignments)) {
                            foreach ($assignments as $assign) {
                                $assignMarks = AssignmentMarks::find('assignment_id = ' . $assign->id);
                                foreach ($assignMarks as $ass_marks) {
                                    $obtainedmark = ($ass_marks->marks) ? $ass_marks->marks : 0;
                                    $obtainedOutOf = ($ass_marks->outof) ? $ass_marks->outof : 0;
                                    if (($obtainedOutOf) > 0) {
                                        $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                    }
                                }

                                $overalstuout ++;
                            }
                            if ($overalstuout > 0) {
                                $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            }
                            $overalsubout ++;
                        }
                    }
                    if ($overalsubout > 0) {
                        $seriesval += round($studentpercentforchart / $overalsubout, 2);
                        $totalarray[] = round($seriesval / count($students), 2);
                    }
                    if ($params['type'] == 'bar' || $params['type'] == 'column' || $params['type'] == 'line') {
                        $data['data'] = '';
                        $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                        $data['color'] = $colors[$i++];
                        $data['name'] = $name;
                        $data['data'] = $totalarray;
                        $maindata[] = $data;
                    }
                }
            }
            if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                $this->view->items = $maindata ? json_encode($maindata) : '';
            }

            $this->view->xaxis = 'Assignment';
            $this->view->node_id = implode(',', $params['aggregateids']);
            $this->view->type = $params['type'];
            $this->view->student = $params['student_list'] ? $params['student_list'] : '';
            $this->view->classroom = $params['compareclassroom'][0] ? $params['compareclassroom'][0] : '';
            $this->view->name = $name = ControllerBase::getNameForKeys(implode(',', $params['aggregateids']));
        }
    }

    public function subwiseAssignmentChartAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $i = 0;
        $subj_Ids = array();
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $seriesval = array();
                $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
                $subj_Ids = array_unique($subj_Ids);
                $studentpercentforchart = array();
                foreach ($subj_Ids as $sub) {
                    $suject = array();
                    $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                    $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                    $overalstuout = $overalstutotalmarks = 0;
                    $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                    if (count($assignments)) {
                        foreach ($assignments as $assign) {
                            $assignMarks = AssignmentMarks::findFirst('assignment_id = ' . $assign->id . ' and student_id = ' . $stud);
                            $obtainedmark = ($assignMarks->marks) ? $assignMarks->marks : 0;
                            $obtainedOutOf = ($assignMarks->outof) ? $assignMarks->outof : 0;
                            if (($obtainedOutOf) > 0) {
                                $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                            }
                            $overalstuout ++;
                        }

                        if ($overalstuout > 0) {
                            $studentpercentforchart[] = round($overalstutotalmarks / $overalstuout, 2);
                        } else {
                            $studentpercentforchart[] = 0;
                        }
                        $subject_name[] = count($subjagg) > 1 ? '<tspan >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '_yes' . '</span></tspan>' : '<tspan >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '_no' . '</span></tspan>';
                    }
                }
                if ($params['type'] == 'bar' || $params['type'] == 'column' || $params['type'] == 'line'):
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $stud_name;
                    $data['data'] = $studentpercentforchart;
                    $maindata[] = $data;
                endif;
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $stucount = explode('-', $nodename->aggregated_nodes_id);
                $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                        . 'stumap.subordinate_key,stumap.status'
                        . ' FROM StudentMapping stumap LEFT JOIN'
                        . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                        . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                $students = $this->modelsManager->executeQuery($stuquery);
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
                $subj_Ids = array_unique($subj_Ids);
                $name = array();
                $name[] = $nodename->name;
//                foreach ($cname as $val) {
//                    $v = explode('>>', $val);
//                    array_shift($v);
//                    $name[] = implode(' >> ', $v);
//                }
                $seriesval = 0;
                $totalarray = array();
                foreach ($subj_Ids as $sub) {
                    $suject = array();
                    $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                    $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                    $overalstuout = $overalstutotalmarks = $studentpercentforchart = 0;
                    $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                    if (count($assignments)) {
                        foreach ($assignments as $assign) {
                            $assignmentMarks = AssignmentMarks::find('assignment_id = ' . $assign->id);
                            foreach ($assignmentMarks as $assmark) {
                                $obtainedmark = ($assmark->marks) ? $assmark->marks : 0;
                                $obtainedOutOf = ($assmark->outof) ? $assmark->outof : 0;
                                if (($obtainedOutOf) > 0) {
                                    $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                }
                            }
                            $overalstuout ++;
                        }

                        if ($overalstuout > 0) {
                            $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            $totalarray[] = round($studentpercentforchart / count($students), 2);
                        }
                        $subject_name[] = count($subjagg) > 1 ? '<tspan >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '_yes' . '</span></tspan>' : '<tspan >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '_no' . '</span></tspan>';
                    }
                }
                if ($params['type'] == 'bar' || $params['type'] == 'column' || $params['type'] == 'line'):
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $name;
                    $data['data'] = $totalarray;
                    $maindata[] = $data;
                endif;
            }
        }

        $this->view->items = $maindata ? json_encode($maindata) : '';
        $arryval = array_values($subject_name);
        $this->view->classtest = json_encode($arryval);
        $this->view->node_id = $params['node_id'];
        $this->view->type = $params['type'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
    }

    public function submoduleAssignmentChartAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }

        $this->view->sub_header = $sub_name = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id']);
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $i = 0;
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $studentpercentforchart = array();
                $suject = array();
                $subjagg = $this->find_childtreevaljson($params['subject_id']);
                $subject = explode(',', $suject);
                foreach ($subjagg as $sub) {
                    $overalstuout = $overalstutotalmarks = 0;
                    $module_name = ControllerBase::getNameForKeys($sub);
                    $a = explode($sub_name->name . '>>', $module_name[0]);
                    $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules =' . $sub);
                    if (count($assignments)) {
                        foreach ($assignments as $assignment) {
                            $assignmentMarks = AssignmentMarks::findFirst('assignment_id = ' . $assignment->id . ' and student_id = ' . $stud);
                            $obtainedmark = ($assignmentMarks->marks) ? $assignmentMarks->marks : 0;
                            $obtainedOutOf = ($assignmentMarks->outof) ? $assignmentMarks->outof : 0;
                            if (($obtainedOutOf) > 0) {
                                $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                            }
                            $overalstuout ++;
                        }

                        if ($overalstuout > 0) {
                            $studentpercentforchart[] = round($overalstutotalmarks / $overalstuout, 2);
                        } else {
                            $studentpercentforchart[] = 0;
                        }

                        $subject_name[] = '<tspan >' . end($a) . '<span style="display:none;">?' . $sub . '</span></tspan>';
                    }
                }
                if ($params['type'] == 'bar' || $params['type'] == 'column' || $params['type'] == 'line'):
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $stud_name;
                    $data['data'] = $studentpercentforchart;
                    $maindata[] = $data;
                endif;
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $stucount = explode('-', $nodename->aggregated_nodes_id);
                $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                        . 'stumap.subordinate_key,stumap.status'
                        . ' FROM StudentMapping stumap LEFT JOIN'
                        . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                        . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                $students = $this->modelsManager->executeQuery($stuquery);
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $name = array();
                $name[] = $nodename->name;
//                foreach ($cname as $val) {
//                    $v = explode('>>', $val);
//                    array_shift($v);
//                    $name[] = implode(' >> ', $v);
//                }
                $totalarray = array();
                $studentpercentforchart = 0;
                $suject = array();
                $subjagg = $this->find_childtreevaljson($params['subject_id']);
                foreach ($subjagg as $sub) {
                    $module_name = ControllerBase::getNameForKeys($sub);
                    $a = explode($sub_name->name . '>>', $module_name[0]);
                    $overalstuout = $overalstutotalmarks = 0;
                    $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules =' . $sub);
                    if (count($assignments)) {
                        foreach ($assignments as $assignment) {
                            $assignmentMarks = AssignmentMarks::find('assignment_id = ' . $assignment->id);
                            foreach ($assignmentMarks as $assmarks) {
                                $obtainedmark = ($assmarks->marks) ? $assmarks->marks : 0;
                                $obtainedOutOf = ($assmarks->outof) ? $assmarks->outof : 0;
                                if (($obtainedOutOf) > 0) {
                                    $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                }
                            }
                            $overalstuout ++;
                        }

                        if ($overalstuout > 0) {
                            $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            $totalarray[] = round($studentpercentforchart / count($students), 2);
                        }
                        $subject_name[] = '<tspan >' . end($a) . '<span style="display:none;">?' . $sub . '</span></tspan>';
                    }
                }
                if ($params['type'] == 'bar' || $params['type'] == 'column' || $params['type']):
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $name;
                    $data['data'] = $totalarray;
                    $maindata[] = $data;
                endif;
            }
        }
        $this->view->items = $maindata ? json_encode($maindata) : '';
        $arryval = array_values($subject_name);
        $this->view->classtest = json_encode($arryval);
        $this->view->node_id = $params['node_id'];
        $this->view->type = $params['type'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
    }

    public function individualAssignmentChartAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }

        if ($params['sub_main_id']) {
            $module_name = ControllerBase::getNameForKeys($params['subject_id']);
            $sub_name = OrganizationalStructureValues::findFirst('id = ' . $params['sub_main_id']);
            $val = explode($sub_name->name . '>>', $module_name[0]);
            $this->view->sub_header = $sub_name->name . '>>' . $val[1];
        } else {
            $sub_name = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id']);
            $this->view->sub_header = $sub_name->name;
        }
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $i = 0;
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $overalstutotalmarks = array();
                $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules =' . $params['subject_id']);
                foreach ($assignments as $assignment) {
                    $assignmarks = AssignmentMarks::findFirst('assignment_id = ' . $assignment->id . ' and student_id = ' . $stud);
                    $obtainedmark = ($assignmarks->marks) ? $assignmarks->marks : 0;
                    $obtainedOutOf = ($assignmarks->outof) ? $assignmarks->outof : 0;
                    if (($obtainedOutOf) > 0) {
                        $overalstutotalmarks[] = ($obtainedmark / $obtainedOutOf * 100);
                    } else {
                        $overalstutotalmarks[] = 0;
                    }
                    $test_name[] = $assignment->topic;
                }

                if ($params['type'] == 'bar' || $params['type'] == 'column' || $params['type'] == 'line'):
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $stud_name;
                    $data['data'] = $overalstutotalmarks;
                    $maindata[] = $data;
                endif;
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $stucount = explode('-', $nodename->aggregated_nodes_id);
                $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                        . 'stumap.subordinate_key,stumap.status'
                        . ' FROM StudentMapping stumap LEFT JOIN'
                        . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                        . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                $students = $this->modelsManager->executeQuery($stuquery);
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $name = array();
                $name[] = $nodename->name;
//                foreach ($cname as $val) {
//                    $v = explode('>>', $val);
//                    array_shift($v);
//                    $name[] = implode(' >> ', $v);
//                }

                $totalarray = array();
                $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules =' . $params['subject_id']);
                foreach ($assignments as $assignment) {
                    $overalstuout = $overalstutotalmarks = $studentpercentforchart = 0;
                    $assignmentMarks = AssignmentMarks::find('assignment_id = ' . $assignment->id);
                    foreach ($assignmentMarks as $assmarks) {
                        $obtainedmark = ($assmarks->marks) ? $assmarks->marks : 0;
                        $obtainedOutOf = ($assmarks->outof) ? $assmarks->outof : 0;
                        if (($obtainedOutOf) > 0) {
                            $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                        }
                        $overalstuout ++;
                    }
                    if ($overalstuout > 0) {
                        $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                        $totalarray[] = round($studentpercentforchart / count($students), 2);
                    } else {
                        $totalarray[] = 0;
                    }
                    $test_name[] = $assignment->topic;
                }
                if ($params['type'] == 'bar' || $params['type'] == 'column' || $params['type'] == 'line'):
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $name;
                    $data['data'] = $totalarray;
                    $maindata[] = $data;
                endif;
            }
        }
        $this->view->items = $maindata ? json_encode($maindata) : '';
        $arryval = array_values($test_name);
        $this->view->classtest = json_encode($arryval);
    }

    public function loadMainexamExcelAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $queryParams = array();
        foreach ($this->request->getPost() as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        $subjpids = ControllerBase::getAlSubjChildNodes($params['aggregateids']);
        $subjids = ControllerBase::getGrpSubjMasPossiblitiesold($params['aggregateids']);
        $subjects = ControllerBase::getAllPossibleSubjectsold($subjpids);
        $subj_Ids = array();
        if (count($subjects) > 0) {
            foreach ($subjects as $nodes) {
                $subj_Ids[] = $nodes->id;
            }
        }
        $this->view->subject_id = $subj_Ids;
        $res = ControllerBase::buildStudentQuery(implode(',', $params['aggregateids']));
        $stuquery = 'SELECT stuhis.id,stuhis.student_info_id,stuhis.aggregate_key,stuhis.status'
                . ' FROM StudentHistory stuhis LEFT JOIN'
                . ' StudentInfo stuinfo ON stuinfo.id=stuhis.student_info_id WHERE '
                . '(' . implode(' and ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
        $this->view->students = $students = $this->modelsManager->executeQuery($stuquery);
        $this->view->mainExId = $params['mainexam'];
        $this->view->stumapdet = $students;
        $this->view->sub_mas_id = implode(',', $subjids);
        $this->view->aggregateids = $params['aggregateids'];

        $this->view->mainexamdet = Mainexam::findFirstById($params['mainexam']);

        if (count($params['aggregateids']) > 0) {

            foreach ($params['aggregateids'] as $path) {
                $orgnztn_str_det = OrganizationalStructureValues::findfirst('id = ' . $path);
                $orgnztn_str_mas_det = OrganizationalStructureMaster::findFirstById($orgnztn_str_det->org_master_id);
                $path_name[$orgnztn_str_mas_det->name] = $orgnztn_str_det->name;
            }
        }

        array_shift($path_name);
        $this->view->classname = $classname = implode('-', $path_name);

        $classlablexpld = explode('-', $classname);
        $this->view->classlablename = array_search($classlablexpld[0], $path_name);
    }

    public function classTestExcelReportAction() {
        $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
        $params = $queryParams = $clststQury = array();
        $val = json_decode($this->request->getPost('params'));
        foreach ($val as $key => $value) {
            $IsSubdiv = explode('_', $key);
            if ($IsSubdiv[0] == 'aggregate' && $value) {
                $params['aggregateids'][] = $value;
            } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                $params['subjaggregate'][] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        $res = $this->clsTestReportArr($params);
        $subjects = $res['subjects'];
        $stumapdet = $res['stumapdet'];
        $clstest = $res['clstest'];
        $header = array();
        $header[] = 'Students';
        $reportdata = array();
        foreach ($clstest as $tst):
            $totmark = ClassTestMarks::findFirst('class_test_id = ' . $tst->class_test_id);
            $header[] = $tst->class_test_name . '(' . $totmark->outof . ')';
        endforeach;
        foreach ($stumapdet as $stu) {
            $stuGenInfo = StudentInfo::findFirstById($stu->student_info_id);
            $reportval = array();
            $reportval[] = $stuGenInfo->Student_Name;
            foreach ($clstest as $tst):
                $stumark = ClassTestMarks::findFirst('class_test_id = ' . $tst->class_test_id . ' and student_id =' . $stu->student_info_id);
                $reportval[] = $stumark ? $stumark->marks : '';
            endforeach;
            $reportdata[] = $reportval;
        }
        $filename = 'Student_Class_Test_' . date('d-m-Y') . '.csv';
        $param['filename'] = $filename;
        $param['header'] = $header;
        $param['data'] = $reportdata;
        $this->generateXcel($param);
    }

    public function subwiseClasstestChartPieAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $i = $k = 0;
        $j = 0;
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $subj_Ids = array();
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
            }
        }

        $subj_Ids = array_unique($subj_Ids);
        $cnt = count($subj_Ids);
        $k = 100;
        $a = 100;
        $seriescounter = 0;
        foreach ($subj_Ids as $sub) {
            $i = 0;
            $slicecounter = 0;
            $arrdata = array();
            if ($students) {
                foreach ($students as $studnt) {
                    $subjids = $subjagg = array();
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                    $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                    $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                    if (count($classtests)) {
                        $overalstuout = $overalstutotalmarks = 0;
                        $studentpercentforchart = array();
                        $stud_name = StudentInfo::findFirstById($studnt)->Student_Name;
                        foreach ($classtests as $classtest) {
                            $clsTstMarks = ClassTestMarks::findFirst('class_test_id = ' . $classtest->class_test_id . ' and student_id = ' . $studnt);
                            $obtainedmark = ($clsTstMarks->marks) ? $clsTstMarks->marks : 0;
                            $obtainedOutOf = ($clsTstMarks->outof) ? $clsTstMarks->outof : 0;
                            if (($obtainedOutOf) > 0) {
                                $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                            }
                            $overalstuout ++;
                        }
                        if ($overalstuout > 0) {
                            $studentpercentforchart[] = round($overalstutotalmarks / $overalstuout, 2);
                        } else {
                            $studentpercentforchart[] = 0;
                        }
                        $subject_name[] = $sub_name->name;
                        if ($studentpercentforchart):
                            $j = !$arrdata['center'] ? ($j + $k) : $j;
                            $arrdata['type'] = 'pie';
                            $arrdata['size'] = $a;

                            $arrdata['center'] = [$j, null];
                            $arrdata['name'] = $sub_name->name;
                            $arrdata['dataLabels']['enabled'] = true;
                            $arrdata['dataLabels']['distance'] = -30;
                            $arrdata['dataLabels']['x'] = -10;
                            $arrdata['dataLabels']['y'] = 70;
                            $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $sub_name->name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                            if ($seriescounter == 0) {
                                $arrdata['showInLegend'] = true;
                            }
                            $colorval = round($studnt % 10);
                            $data['color'] = $colors[$colorval];
                            $data['slicecounter'] = $slicecounter++;
                            $arrdata['html'] = $sub_name->name;
                            $data['name'] = $stud_name;
                            $data['y'] = array_shift($studentpercentforchart);
                            $data['sub_id'] = $sub_name->id;
                            $data['sub_id_count'] = count($subjagg) > 1 ? 1 : 0;
                            $arrdata['data'][] = $data;
                        endif;
                    }
                }
            }
            if ($compareclassroom) {
                foreach ($compareclassroom as $clsroom) {
                    $subjids = $subjagg = array();
                    $nodename = ClassroomMaster::findFirstById($clsroom);
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $student = $this->modelsManager->executeQuery($stuquery);
                    $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                    $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                    $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                    if (count($classtests)) {
                        $totalarray = array();
                        $studentpercentforchart = 0;
                        $overalstuout = $overalstutotalmarks = 0;
                        foreach ($classtests as $classtest) {
                            $clsTstMarks = ClassTestMarks::find('class_test_id = ' . $classtest->class_test_id);
                            foreach ($clsTstMarks as $clsmark) {
                                $obtainedmark = ($clsmark->marks) ? $clsmark->marks : 0;
                                $obtainedOutOf = ($clsmark->outof) ? $clsmark->outof : 0;
                                if (($obtainedOutOf) > 0) {
                                    $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                }
                            }
                            $overalstuout ++;
                        }
                        if ($overalstuout > 0) {
                            $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            $totalarray[] = round($studentpercentforchart / count($student), 2);
                        }
                        $subject_name[] = $sub_name->name;
                        if ($totalarray):
                            $j = !$arrdata['center'] ? ($j + $k) : $j;
                            $arrdata['type'] = 'pie';
                            $arrdata['size'] = $a;
                            $arrdata['center'] = [$j, null];
                            $arrdata['name'] = $sub_name->name;
                            $arrdata['dataLabels']['enabled'] = true;
                            $arrdata['dataLabels']['distance'] = -30;
                            $arrdata['dataLabels']['x'] = -10;
                            $arrdata['dataLabels']['y'] = 70;
                            $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $sub_name->name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                            if ($seriescounter == 0) {
                                $arrdata['showInLegend'] = true;
                            }
                            $colorval = round($clsroom % 10);
                            $data['color'] = $colors[$colorval];
                            $data['slicecounter'] = $slicecounter++;
                            $arrdata['html'] = $sub_name->name;
                            $arrdata['id'] = $sub_name->id;
                            $data['color'] = $colors[$i++];
                            $data['name'] = $nodename->name;
                            $data['y'] = array_shift($totalarray);
                            $data['sub_id'] = $sub_name->id;
                            $data['sub_id_count'] = count($subjagg) > 1 ? 1 : 0;
                            $arrdata['data'][] = $data;
                        endif;
                    }
                }
            }
            if (count($arrdata) > 0) {
                $maindata[] = $arrdata;
            }
            $seriescounter++;
            $k = 200;
        }

        $this->view->items = $maindata ? (str_replace(array('"%', '%"', '\r\n'), '', json_encode($maindata))) : '';
        $this->view->node_id = $params['node_id'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
        $arry = array_values(array_unique($subject_name));
        $this->view->classtest = json_encode($arry);
    }

    public function individualClasstestChartPieAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $i = $k = $j = 0;
        $subj_mas_Ids = array();
        $sub_name = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id']);
        $this->view->sub_header = $sub_name->name;
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                foreach ($subjids as $id) {
                    $subj_mas_Ids[] = $id;
                }
            }
        }

        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subj_mas_Ids[] = implode(',', $subjids);
//                $subjectsid = GroupSubjectsTeachers::find('subject_id =' . $params['subject_id'] . ' and classroom_master_id =' . $cvalue);
//                foreach ($subjectsid as $svalue) {
//                    $subj_mas_Ids[] = $svalue->id;
//                }
            }
        }

        $subj_mas_Ids = array_unique($subj_mas_Ids);
        $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subj_mas_Ids) . ') and subjct_modules =' . $params['subject_id']);
        $cnt = count($classtests);
        $k = 100;
        $a = 100;
        $seriescounter = 0;
        foreach ($classtests as $classtest) {
            $i = 0;
            $slicecounter = 0;
            $arrdata = '';
            if ($students) {
                foreach ($students as $studt) {
                    $overalstutotalmarks = 0;
                    $stud_name = StudentInfo::findFirstById($studt)->Student_Name;
                    $clsTstMarks = ClassTestMarks::findFirst('class_test_id = ' . $classtest->class_test_id . ' and student_id = ' . $studt);

                    $obtainedmark = ($clsTstMarks->marks) ? $clsTstMarks->marks : 0;
                    $obtainedOutOf = ($clsTstMarks->outof) ? $clsTstMarks->outof : 0;
                    if (($obtainedOutOf) > 0) {
                        $overalstutotalmarks = ($obtainedmark / $obtainedOutOf * 100);
                    } else {
                        $overalstutotalmarks = 0;
                    }
                    if ($overalstutotalmarks != 0) {
                        $j = !$arrdata['center'] ? ($j + $k) : $j;
                        $arrdata['type'] = 'pie';
                        $arrdata['size'] = $a;
                        $arrdata['center'] = [$j, null];
                        $arrdata['name'] = $classtest->class_test_name;
                        $arrdata['dataLabels']['enabled'] = true;
                        $arrdata['dataLabels']['distance'] = -30;
                        $arrdata['dataLabels']['x'] = -10;
                        $arrdata['dataLabels']['y'] = 70;
                        $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $classtest->class_test_name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                        if ($seriescounter == 0) {
                            $arrdata['showInLegend'] = true;
                        }
                        $colorval = round($studt % 10);
                        $data['color'] = $colors[$colorval];
                        $data['slicecounter'] = $slicecounter++;
                        $data['name'] = $stud_name;
                        $data['y'] = $overalstutotalmarks;
                        $arrdata['data'][] = $data;
                    }
                }
            }
            if ($compareclassroom) {
                foreach ($compareclassroom as $clsvalue) {
                    $subj_mas = array();
                    $nodename = ClassroomMaster::findFirstById($clsvalue);
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                    $subj_mas[] = implode(',', $subjids);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $student = $this->modelsManager->executeQuery($stuquery);
                    $totalarray = '';
                    $overalstuout = $overalstutotalmarks = 0;
                    $studentpercentforchart = 0;
                    $classtst = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subj_mas) . ') and subjct_modules =' . $params['subject_id']
                                    . ' and class_test_id = ' . $classtest->class_test_id);
                    if (count($classtst) > 0) {
                        $clsTstMarks = ClassTestMarks::find('class_test_id = ' . $classtest->class_test_id);
                        if (count($clsTstMarks) > 0) {
                            foreach ($clsTstMarks as $clsmark) {
                                $obtainedmark = ($clsmark->marks) ? $clsmark->marks : 0;
                                $obtainedOutOf = ($clsmark->outof) ? $clsmark->outof : 0;
                                if (($obtainedOutOf) > 0) {
                                    $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                }
                                $overalstuout ++;
                            }
                        }
                    }
                    if ($overalstuout > 0) {
                        $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                        $totalarray = round($studentpercentforchart / count($student), 2);
                    } else {
                        $totalarray = 0;
                    }

                    if ($totalarray != 0) {
                        if ($params['type']) {
                            $j = !$arrdata['center'] ? ($j + $k) : $j;
                            $arrdata['type'] = 'pie';
                            $arrdata['size'] = $a;
                            $arrdata['center'] = [$j, null];
                            $arrdata['name'] = $classtest->class_test_name;
                            $arrdata['dataLabels']['enabled'] = true;
                            $arrdata['dataLabels']['distance'] = -30;
                            $arrdata['dataLabels']['x'] = -10;
                            $arrdata['dataLabels']['y'] = 70;
                            $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $classtest->class_test_name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                            if ($seriescounter == 0) {
                                $arrdata['showInLegend'] = true;
                            }
                            $colorval = round($clsvalue % 10);
                            $data['color'] = $colors[$colorval];
                            $data['slicecounter'] = $slicecounter++;
                            $data['name'] = $nodename->name;
                            $data['y'] = $totalarray;
                            $arrdata['data'][] = $data;
                        }
                    }
                }
            }
            if (count($arrdata) > 0) {
                $maindata[] = $arrdata;
            }
            $seriescounter++;
            $k = 200;
        }

        $this->view->items = $maindata ? (str_replace(array('"%', '%"', '\r\n'), '', json_encode($maindata))) : '';
    }

    public function submoduleClasstestChartPieAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $this->view->sub_header = $sub_name = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id']);
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $i = $k = $j = $a = 0;
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $subj_mas_Ids = array();
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subj_mas_Ids[] = implode(',', $subjids);
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subj_mas_Ids[] = implode(',', $subjids);
            }
        }
        $subj_mas_Ids = array_unique($subj_mas_Ids);
        $subjagg = $this->find_childtreevaljson($params['subject_id']);
        $cnt = count($subjagg);
        $k = $a = 100;
        foreach ($subjagg as $sub) {
            $module_name = ControllerBase::getNameForKeys($sub);
            $a = explode($sub_name->name . '>>', $module_name[0]);
            $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subj_mas_Ids) . ') and subjct_modules =' . $sub);
            if ($students) {
                foreach ($students as $stud) {
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $studentpercentforchart = 0;
                    $overalstuout = $overalstutotalmarks = 0;
                    if (count($classtests)) {
                        foreach ($classtests as $classtest) {
                            $clsTstMarks = ClassTestMarks::findFirst('class_test_id = ' . $classtest->class_test_id . ' and student_id = ' . $stud);
                            $obtainedmark = ($clsTstMarks->marks) ? $clsTstMarks->marks : 0;
                            $obtainedOutOf = ($clsTstMarks->outof) ? $clsTstMarks->outof : 0;
                            if (($obtainedOutOf) > 0) {
                                $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                            }
                            $overalstuout ++;
                        }

                        if ($overalstuout > 0) {
                            $studentpercentforchart = round($overalstutotalmarks / $overalstuout, 2);
                        }
                        if ($studentpercentforchart > 0) {
                            if ($params['type']) {
                                $j = !$arrdata['center'] ? ($j + $k) : $j;
                                $arrdata['type'] = 'pie';
                                $arrdata['size'] = $a;
                                $arrdata['center'] = [$j, 100];
                                $arrdata['name'] = end($a);
                                $data['color'] = $colors[$i++];
                                $data['name'] = $stud_name;
                                $data['sub_id'] = $sub;
                                $data['y'] = $studentpercentforchart;
                                $arrdata['data'][] = $data;
                            }
                        }
                    }
                }
            }
            if ($compareclassroom) {
                foreach ($compareclassroom as $cvalue) {
                    $studentpercentforchart = $totalarray = 0;
                    $overalstuout = $overalstutotalmarks = 0;
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $student = $this->modelsManager->executeQuery($stuquery);
                    if (count($classtests)) {
                        foreach ($classtests as $classtest) {
                            $clsTstMarks = ClassTestMarks::find('class_test_id = ' . $classtest->class_test_id);
                            foreach ($clsTstMarks as $clsmark) {
                                $obtainedmark = ($clsmark->marks) ? $clsmark->marks : 0;
                                $obtainedOutOf = ($clsmark->outof) ? $clsmark->outof : 0;
                                if (($obtainedOutOf) > 0) {
                                    $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                }
                            }
                            $overalstuout ++;
                        }

                        if ($overalstuout > 0) {
                            $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            $totalarray = round($studentpercentforchart / count($student), 2);
                        }
                        if ($totalarray > 0) {
                            if ($params['type']) {
                                $j = !$arrdata['center'] ? ($j + $k) : $j;
                                $arrdata['type'] = 'pie';
                                $arrdata['size'] = $a;
                                $arrdata['center'] = [$j, 100];
                                $arrdata['name'] = end($a);
                                $data['color'] = $colors[$i++];
                                $data['sub_id'] = $sub;
                                $data['name'] = $nodename->name;
                                $data['y'] = $totalarray;
                                $arrdata['data'][] = $data;
                            }
                        }
                    }
                }
            }
            $maindata[] = $arrdata;
            $k = 200;
        }

        $this->view->items = $maindata ? json_encode($maindata) : '';
        $this->view->node_id = $params['node_id'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
    }

    public function subwiseAssignmentChartPieAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }

        $i = $k = 0;
        $j = 0;
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $subj_Ids = array();
        $subj_mas_Ids = array();
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
            }
        }
        $subj_Ids = array_unique($subj_Ids);
        $cnt = count($subj_Ids);
        $k = 100;
        $a = 100;
        $seriescounter = 0;
        foreach ($subj_Ids as $sub) {
            $arrdata = array();
            if ($params['student']) {
                $students = explode(',', $params['student']);
                $slicecounter = 0;
                foreach ($students as $stud) {
                    $subjids = array();
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $overalstuout = $overalstutotalmarks = $studentpercentforchart = 0;
                    $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                    $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                    $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                    if (count($assignments)) {
                        foreach ($assignments as $assign) {
                            $assignMarks = AssignmentMarks::findFirst('assignment_id = ' . $assign->id . ' and student_id = ' . $stud);
                            $obtainedmark = ($assignMarks->marks) ? $assignMarks->marks : 0;
                            $obtainedOutOf = ($assignMarks->outof) ? $assignMarks->outof : 0;
                            if (($obtainedOutOf) > 0) {
                                $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                            }
                            $overalstuout ++;
                        }

                        if ($overalstuout > 0) {
                            $studentpercentforchart = round($overalstutotalmarks / $overalstuout, 2);
                        }
                        if ($studentpercentforchart > 0):
                            $j = !$arrdata['center'] ? ($j + $k) : $j;
                            $arrdata['type'] = 'pie';
                            $arrdata['size'] = $a;
                            $arrdata['center'] = [$j, null];
                            $arrdata['name'] = $sub_name->name;
                            $arrdata['dataLabels']['enabled'] = true;
                            $arrdata['dataLabels']['distance'] = -30;
                            $arrdata['dataLabels']['x'] = -10;
                            $arrdata['dataLabels']['y'] = 70;
                            $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $sub_name->name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                            if ($seriescounter == 0) {
                                $arrdata['showInLegend'] = true;
                            }
                            $colorval = round($stud % 10);
                            $data['color'] = $colors[$colorval];
                            $data['slicecounter'] = $slicecounter++;
                            $data['name'] = $stud_name;
                            $data['y'] = $studentpercentforchart;
                            $data['sub_id'] = $sub_name->id;
                            $data['sub_id_count'] = count($subjagg) > 1 ? 1 : 0;
                            $arrdata['data'][] = $data;
                        endif;
                    }
                }
            }
            if ($params['classroom']) {
                $compareclassroom = explode(',', $params['classroom']);
                foreach ($compareclassroom as $cvalue) {
                    $subjids = array();
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $student = $this->modelsManager->executeQuery($stuquery);
                    $overalstuout = $overalstutotalmarks = $studentpercentforchart = $totalarray = 0;
                    $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                    $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                    $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                    if (count($assignments)) {
                        foreach ($assignments as $assign) {
                            $assignmentMarks = AssignmentMarks::find('assignment_id = ' . $assign->id);
                            foreach ($assignmentMarks as $assmark) {
                                $obtainedmark = ($assmark->marks) ? $assmark->marks : 0;
                                $obtainedOutOf = ($assmark->outof) ? $assmark->outof : 0;
                                if (($obtainedOutOf) > 0) {
                                    $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                }
                            }
                            $overalstuout ++;
                        }

                        if ($overalstuout > 0) {
                            $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            $totalarray = round($studentpercentforchart / count($student), 2);
                        }
                        if ($totalarray > 0):
                            $j = !$arrdata['center'] ? ($j + $k) : $j;
                            $arrdata['type'] = 'pie';
                            $arrdata['size'] = $a;
                            $arrdata['center'] = [$j, null];
                            $arrdata['name'] = $sub_name->name;
                            $arrdata['dataLabels']['enabled'] = true;
                            $arrdata['dataLabels']['distance'] = -30;
                            $arrdata['dataLabels']['x'] = -10;
                            $arrdata['dataLabels']['y'] = 70;
                            $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $sub_name->name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                            if ($seriescounter == 0) {
                                $arrdata['showInLegend'] = true;
                            }
                            $colorval = round($cvalue % 10);
                            $data['color'] = $colors[$colorval];
                            $data['slicecounter'] = $slicecounter++;
                            $data['name'] = $nodename->name;
                            $data['y'] = $totalarray;
                            $data['sub_id'] = $sub_name->id;
                            $data['sub_id_count'] = count($subjagg) > 1 ? 1 : 0;
                            $arrdata['data'][] = $data;
                        endif;
                    }
                }
            }
            if (count($arrdata) > 0) {
                $maindata[] = $arrdata;
            }
            $seriescounter++;
            $k = 200;
        }

        $this->view->items = $maindata ? (str_replace(array('"%', '%"', '\r\n'), '', json_encode($maindata))) : '';
        $this->view->node_id = $params['node_id'];
        $this->view->type = $params['type'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
    }

    public function individualAssignmentChartPieAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $i = $k = $j = 0;
        $subj_mas_Ids = array();
        $sub_name = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id']);
        $this->view->sub_header = $sub_name->name;
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subj_mas_Ids[] = implode(',', $subjids);
            }
        } if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subj_mas_Ids[] = implode(',', $subjids);
            }
        }
        $subj_mas_Ids = array_unique($subj_mas_Ids);
        $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subj_mas_Ids) . ') and subjct_modules =' . $params['subject_id']);
        $cnt = count($assignments);

        $k = 100;
        $a = 100;
        $seriescounter = 0;
        if ($assignments) {
            foreach ($assignments as $assignment) {
                $slicecounter = 0;
                $i = 0;
                $arrdata = array();
                if ($params['student']) {
                    $students = explode(',', $params['student']);
                    foreach ($students as $stud) {
                        $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                        $overalstutotalmarks = 0;
                        $assignmarks = AssignmentMarks::findFirst('assignment_id = ' . $assignment->id . ' and student_id = ' . $stud);
                        $obtainedmark = ($assignmarks->marks) ? $assignmarks->marks : 0;
                        $obtainedOutOf = ($assignmarks->outof) ? $assignmarks->outof : 0;
                        if (($obtainedOutOf) > 0) {
                            $overalstutotalmarks = ($obtainedmark / $obtainedOutOf * 100);
                        } else {
                            $overalstutotalmarks = 0;
                        }
                        if ($overalstutotalmarks > 0) {
                            $j = !$arrdata['center'] ? ($j + $k) : $j;
                            $arrdata['type'] = 'pie';
                            $arrdata['size'] = $a;
                            $arrdata['center'] = [$j, null];
                            $arrdata['name'] = $assignment->topic;
                            $arrdata['dataLabels']['enabled'] = true;
                            $arrdata['dataLabels']['distance'] = -30;
                            $arrdata['dataLabels']['x'] = -10;
                            $arrdata['dataLabels']['y'] = 70;
                            $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $assignment->topic . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                            if ($seriescounter == 0) {
                                $arrdata['showInLegend'] = true;
                            }
                            $colorval = round($stud % 10);
                            $data['color'] = $colors[$colorval];
                            $data['slicecounter'] = $slicecounter++;
                            $data['name'] = $stud_name;
                            $data['y'] = $overalstutotalmarks;
                            $arrdata['data'][] = $data;
                        }
                    }
                }
                if ($params['classroom']) {
                    $compareclassroom = explode(',', $params['classroom']);
                    foreach ($compareclassroom as $cvalue) {
                        $subj_mas = array();
                        $nodename = ClassroomMaster::findFirstById($cvalue);
                        $nodename = ClassroomMaster::findFirstById($cvalue);
                        $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                        $subj_mas[] = implode(',', $subjids);
                        $stucount = explode('-', $nodename->aggregated_nodes_id);
                        $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                        $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                                . 'stumap.subordinate_key,stumap.status'
                                . ' FROM StudentMapping stumap LEFT JOIN'
                                . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                                . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                        $student = $this->modelsManager->executeQuery($stuquery);
                        $studentpercentforchart = $totalarray = 0;
                        $overalstuout = $overalstutotalmarks = 0;

                        $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subj_mas) . ') and subjct_modules =' . $params['subject_id'] .
                                        ' and id =' . $assignment->id);

                        if (count($assignments) > 0) {
                            $assignmentMarks = AssignmentMarks::find('assignment_id = ' . $assignment->id);
                            foreach ($assignmentMarks as $assmarks) {
                                $obtainedmark = ($assmarks->marks) ? $assmarks->marks : 0;
                                $obtainedOutOf = ($assmarks->outof) ? $assmarks->outof : 0;
                                if (($obtainedOutOf) > 0) {
                                    $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                }
                                $overalstuout ++;
                            }
                        }
                        if ($overalstuout > 0) {
                            $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            $totalarray = round($studentpercentforchart / count($student), 2);
                        }
                        if ($totalarray > 0) {
                            $j = !$arrdata['center'] ? ($j + $k) : $j;
                            $arrdata['type'] = 'pie';
                            $arrdata['size'] = $a;
                            $arrdata['center'] = [$j, null];
                            $arrdata['name'] = $assignment->topic;
                            $arrdata['dataLabels']['enabled'] = true;
                            $arrdata['dataLabels']['distance'] = -30;
                            $arrdata['dataLabels']['x'] = -10;
                            $arrdata['dataLabels']['y'] = 70;
                            $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $assignment->topic . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                            if ($seriescounter == 0) {
                                $arrdata['showInLegend'] = true;
                            }
                            $colorval = round($cvalue % 10);
                            $data['color'] = $colors[$colorval];
                            $data['slicecounter'] = $slicecounter++;
                            $data['name'] = $nodename->name;
                            $data['y'] = $totalarray;
                            $arrdata['data'][] = $data;
                        }
                    }
                }
                if (count($arrdata) > 0) {
                    $maindata[] = $arrdata;
                }
                $seriescounter++;
                $k = 200;
            }
        }

        $this->view->items = $maindata ? (str_replace(array('"%', '%"', '\r\n'), '', json_encode($maindata))) : '';
    }

    public function submoduleAssignmentChartPieAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $this->view->sub_header = $sub_name = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id']);
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $i = $k = $j = 0;
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $subj_mas_Ids = array();
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subj_mas_Ids[] = implode(',', $subjids);
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subj_mas_Ids[] = implode(',', $subjids);
            }
        }
        $subj_mas_Ids = array_unique($subj_mas_Ids);
        $subjagg = $this->find_childtreevaljson($params['subject_id']);
        $cnt = count($subjagg);

        $k = $a = 100;

        foreach ($subjagg as $sub) {
            $arrdata = array();
            $module_name = ControllerBase::getNameForKeys($sub);
            $a = explode($sub_name->name . '>>', $module_name[0]);
            $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules =' . $sub);
            if (count($assignments)) {
                if ($params['student']) {
                    $students = explode(',', $params['student']);
                    foreach ($students as $stud) {
                        $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                        $overalstuout = $overalstutotalmarks = $studentpercentforchart = 0;
                        foreach ($assignments as $assignment) {
                            $assignmentMarks = AssignmentMarks::findFirst('assignment_id = ' . $assignment->id . ' and student_id = ' . $stud);
                            $obtainedmark = ($assignmentMarks->marks) ? $assignmentMarks->marks : 0;
                            $obtainedOutOf = ($assignmentMarks->outof) ? $assignmentMarks->outof : 0;
                            if (($obtainedOutOf) > 0) {
                                $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                            }
                            $overalstuout ++;
                        }

                        if ($overalstuout > 0) {
                            $studentpercentforchart = round($overalstutotalmarks / $overalstuout, 2);
                        }
                        if ($studentpercentforchart > 0) {
                            if ($params['type']) {
                                $j = !$arrdata['center'] ? ($j + $k) : $j;
                                $arrdata['type'] = 'pie';
                                $arrdata['size'] = $a;
                                $arrdata['center'] = [$j, 100];
                                $arrdata['name'] = end($a);
                                $data['color'] = $colors[$i++];
                                $data['name'] = $stud_name;
                                $data['sub_id'] = $sub;
                                $data['y'] = $studentpercentforchart;
                                $arrdata['data'][] = $data;
                            }
                        }
                    }
                }
                if ($params['classroom']) {
                    $compareclassroom = explode(',', $params['classroom']);
                    foreach ($compareclassroom as $cvalue) {
                        $nodename = ClassroomMaster::findFirstById($cvalue);
                        $stucount = explode('-', $nodename->aggregated_nodes_id);
                        $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                        $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                                . 'stumap.subordinate_key,stumap.status'
                                . ' FROM StudentMapping stumap LEFT JOIN'
                                . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                                . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                        $student = $this->modelsManager->executeQuery($stuquery);
                        $studentpercentforchart = $totalarray = 0;
                        $overalstuout = $overalstutotalmarks = 0;
                        if (count($assignments)) {
                            foreach ($assignments as $assignment) {
                                $assignmentMarks = AssignmentMarks::find('assignment_id = ' . $assignment->id);
                                foreach ($assignmentMarks as $assmarks) {
                                    $obtainedmark = ($assmarks->marks) ? $assmarks->marks : 0;
                                    $obtainedOutOf = ($assmarks->outof) ? $assmarks->outof : 0;
                                    if (($obtainedOutOf) > 0) {
                                        $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                    }
                                }
                                $overalstuout ++;
                            }

                            if ($overalstuout > 0) {
                                $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                                $totalarray = round($studentpercentforchart / count($student), 2);
                            }
                            if ($totalarray > 0) {
                                if ($params['type']) {
                                    $j = !$arrdata['center'] ? ($j + $k) : $j;
                                    $arrdata['type'] = 'pie';
                                    $arrdata['size'] = $a;
                                    $arrdata['center'] = [$j, 100];
                                    $arrdata['name'] = end($a);
                                    $data['color'] = $colors[$i++];
                                    $data['sub_id'] = $sub;
                                    $data['name'] = $nodename->name;
                                    $data['y'] = $totalarray;
                                    $arrdata['data'][] = $data;
                                }
                            }
                        }
                    }
                }
                if ($arrdata) {
                    $maindata[] = $arrdata;
                }
            }
            $k = 200;
        }

        $this->view->items = $maindata ? json_encode($maindata) : '';
        $this->view->node_id = $params['node_id'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
    }

    public function displayOverallCmprsnChartPieAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $result = $this->MainExamChartFn($params);
        $this->view->items = $result['maindata'] ? (str_replace(array('"%', '%"', '\r\n'), '', json_encode($result['maindata']))) : '';
        $this->view->node_id = $params['node_id'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
        $this->view->compare = $result['compare'];
    }

    public function displaySubwiseCmprsnChartPieAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $i = $j = 0;
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $subj_Ids = array();
        $res = ControllerBase::buildExamQuery($params['node_id']);
        $mainexamdet = Mainexam ::find(implode(' or ', $res));
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
                $subj_mas_Ids[] = implode(',', $subjids);
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
            }
        }
        $subj_Ids = array_unique($subj_Ids);
//        $subj_mas_Ids = array_unique($subj_mas_Ids);
        $cnt = count($subj_Ids);

        $min_val = array();
        $k = 100;
        $a = 200;
        $seriescounter = 0;
        foreach ($subj_Ids as $sub) {
            $i = $slicecounter = 0;
            $arrdata = array();
            $suject = $subjagg = array();
            $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
            $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
            $suject = $this->find_childtreevaljson($sub);
            $cnt = 0;
            $cnt = count($suject);
            $subject = explode(',', $suject);
            $slicecounter = 0;
            if ($params['student']) {
                $students = explode(',', $params['student']);
                foreach ($students as $stud) {
                    $subjids = array();
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $subjids = array();
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $overalstuout = $overalstutotalmarks = $percent = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and  subject_id IN ( ' . implode(',', $subjagg) . ')');
                    foreach ($mainexamMarks as $mainexMark) {
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $overalstutotalmarks += ($obtainedmark / $obtainedoutOf * 100);
                        }
                        $overalstuout ++;
                    }

                    if ($overalstuout > 0) {
                        $percent = round($overalstutotalmarks / $cnt, 2);
                    }
                    if ($percent > 0):
                        $j = !$arrdata['center'] ? ($j + $k) : $j;
                        $arrdata['type'] = 'pie';
                        $arrdata['size'] = 100;
                        $arrdata['center'] = [$j, null];
                        $arrdata['name'] = $sub_name->name;
                        $arrdata['dataLabels']['enabled'] = true;
                        $arrdata['dataLabels']['distance'] = -30;
                        $arrdata['dataLabels']['x'] = -10;
                        $arrdata['dataLabels']['y'] = 70;
                        $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $sub_name->name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                        if ($seriescounter == 0) {
                            $arrdata['showInLegend'] = true;
                            $seriescounter++;
                        }
                        $colorval = round($stud % 10);
                        $data['color'] = $colors[$colorval];
                        $data['slicecounter'] = $slicecounter++;
                        $data['seriescounter'] = $seriescounter;
                        $data['name'] = $stud_name;
                        $data['y'] = $percent;
                        $data['sub_idt'] = $sub_name->id;
                        $data['sub_count'] = count($subjagg) > 1 ? 1 : 0;
                        $arrdata['data'][] = $data;
                    endif;
                }
            }
            if ($params['classroom']) {
                $compareclassroom = explode(',', $params['classroom']);
                foreach ($compareclassroom as $cvalue) {
                    $subj_mas_Ids = array();
                    $stutot = $stuoutof = $stuactoutof = $status = array();
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                    foreach ($submaster as $ssvalue) {
                        $subj_mas_Ids[] = $ssvalue->id;
                    }
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $student = $this->modelsManager->executeQuery($stuquery);
                    $overalstuout = $overalstutotalmarks = $percent = $finalval = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subj_mas_Ids) . ') and subject_id IN ( ' . implode(',', $subjagg) . ')');
                    foreach ($mainexamMarks as $mainexMark) {
//                        $min_val[$sub][$cvalue][$mainexMark->student_id] += (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $actualper = ($obtainedmark / $obtainedoutOf * 100);
                            $overalstutotalmarks += $actualper;
                            $nmark = $obtainedmark / $obtainedoutOf;
                            $stuuniout = $stuoutof[$sub][$cvalue][$mainexMark->student_id] ? $stuoutof[$sub][$cvalue][$mainexMark->student_id] : 0;
                            $stutotmark = $stutot[$sub][$cvalue][$mainexMark->student_id] ? $stutot[$sub][$cvalue][$mainexMark->student_id] : 0;
                            $stutotout = $stuactoutof[$sub][$cvalue][$mainexMark->student_id] ? $stuactoutof[$sub][$cvalue][$mainexMark->student_id] : 0;
                            $stutot[$sub][$cvalue][$mainexMark->student_id] = $stutotmark + $nmark;
                            $stuoutof[$sub][$cvalue][$mainexMark->student_id] = $stuuniout + 1;
                            $stuactoutof[$sub][$cvalue][$mainexMark->student_id] = $stutotout + $obtainedoutOf;
                            $stat = $status[$sub][$cvalue][$mainexMark->student_id] == 'fail' ? 1 : 0;
                            if (!$stat)
                                $status[$sub][$cvalue][$mainexMark->student_id] = ($actualper >= 40 ) ? 'pass' : 'fail';
                        }
                        $overalstuout ++;
                    }
                    if (count($stuoutof) > 0) {
                        foreach ($stutot[$sub][$cvalue] as $key => $stot) {
                            $min_val[$sub][$cvalue][$key]['mark'] = ($stot * $stuactoutof[$sub][$cvalue][$key]) / $stuoutof[$sub][$cvalue][$key];
                            $min_val[$sub][$cvalue][$key]['outof'] = $stuactoutof[$sub][$cvalue][$key];
                            $min_val[$sub][$cvalue][$key]['stuid'] = $key;

                            if ($status[$sub][$cvalue][$key] == 'pass') {
                                $min_val[$sub][$cvalue][$key]['pass'] = $status[$sub][$cvalue][$key];
                            } else {
                                $min_val[$sub][$cvalue][$key]['fail'] = $status[$sub][$cvalue][$key];
                            }
                        }
                    }
                    if ($overalstuout > 0) {
                        $finalval = round($overalstutotalmarks / $cnt, 2);
                        $percent = round($finalval / count($student), 2);
                    }
                    if ($percent > 0):
                        $j = !$arrdata['center'] ? ($j + $k) : $j;
                        $arrdata['type'] = 'pie';
                        $arrdata['size'] = 100;
                        $arrdata['center'] = [$j, null];
                        $arrdata['name'] = $sub_name->name;
                        $arrdata['dataLabels']['enabled'] = true;
                        $arrdata['dataLabels']['distance'] = -30;
                        $arrdata['dataLabels']['x'] = -10;
                        $arrdata['dataLabels']['y'] = 70;
                        $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $sub_name->name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                        if ($seriescounter == 0) {
                            $arrdata['showInLegend'] = true;
                            $seriescounter++;
                        }
                        $colorval = round($cvalue % 10);
                        $data['color'] = $colors[$colorval];
                        $data['slicecounter'] = $slicecounter++;
                        $data['seriescounter'] = $seriescounter;
                        $data['name'] = $nodename->name;
                        $data['y'] = $percent;
                        $data['sub_idt'] = $sub_name->id;
                        $data['sub_count'] = count($subjagg) > 1 ? 1 : 0;
                        $arrdata['data'][] = $data;
                    endif;
                    $compare = $min_val;
                }
            }
            if (count($arrdata) > 0) {
                $maindata[] = $arrdata;
            }
            $k = 200;
            $seriescounter++;
        }
        $this->view->items = $maindata ? (str_replace(array('"%', '%"', '\r\n'), '', json_encode($maindata))) : '';
        $this->view->exam_name = $manex->exam_name;
        $this->view->node_id = $params['node_id'];
        $this->view->type = $params['type'];
        $this->view->exam_id = $params['exam_id'];
        $this->view->compare = $compare;
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
    }

    public function displaySubjctmoduleChartPieAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $i = $j = 0;
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $suject = $this->find_childtreevaljson($params['subject_id']);
        $k = 100;
        $a = 100;
        $min_val = array();
        $seriescounter = 0;
        foreach ($suject as $sub) {
            $i = 0;
            $arrdata = array();
            $sub_name = OrganizationalStructureValues::findFirst('id = ' . $sub);
            $slicecounter = 0;
            if ($params['student']) {
                $students = explode(',', $params['student']);
                foreach ($students as $stud) {
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $overalstuout = $overalstutotalmarks = $percent = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and  subject_id =' . $sub . ' ORDER BY subject_id');
                    foreach ($mainexamMarks as $mainexMark) {
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $overalstutotalmarks += ($obtainedmark / $obtainedoutOf * 100);
                        }
                        $overalstuout ++;
                    }

                    if ($overalstuout > 0) {
                        $percent = round($overalstutotalmarks / $overalstuout, 2);
                    }
                    if ($percent > 0):
                        $j = !$arrdata['center'] ? ($j + $k) : $j;
                        $arrdata['type'] = 'pie';
                        $arrdata['size'] = 100;
                        $arrdata['center'] = [$j, null];
                        $arrdata['name'] = $sub_name->name;
                        $arrdata['dataLabels']['enabled'] = true;
                        $arrdata['dataLabels']['distance'] = -40;
                        $arrdata['dataLabels']['x'] = 0;
                        $arrdata['dataLabels']['y'] = 70;
                        $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $sub_name->name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                        if ($seriescounter == 0) {
                            $arrdata['showInLegend'] = true;
                            $seriescounter ++;
                        }
                        $colorval = round($stud % 10);
                        $data['color'] = $colors[$colorval];
                        $data['slicecounter'] = $slicecounter++;
                        $data['seriescounter'] = $seriescounter;
                        $data['name'] = $stud_name;
                        $data['y'] = $percent;
                        $data['sub_idt'] = $sub_name->id;
                        $data['sub_count'] = count($subjagg) > 1 ? 1 : 0;
                        $arrdata['data'][] = $data;
                    endif;
                }
            }
            if ($params['classroom']) {
                $compareclassroom = explode(',', $params['classroom']);
                foreach ($compareclassroom as $cvalue) {
                    $subj_mas_Ids = array();
                    $stutot = $stuoutof = $stuactoutof = $status = array();
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                    foreach ($submaster as $ssvalue) {
                        $subj_mas_Ids[] = $ssvalue->id;
                    }
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $student = $this->modelsManager->executeQuery($stuquery);
                    $overalstuout = $overalstutotalmarks = $percent = $finalval = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subj_mas_Ids) . ') and subject_id =' . $sub . ' ORDER BY subject_id');

                    foreach ($mainexamMarks as $mainexMark) {
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $actualper = ($obtainedmark / $obtainedoutOf * 100);
                            $overalstutotalmarks += $actualper;
                            $nmark = $obtainedmark / $obtainedoutOf;
                            $stuuniout = $stuoutof[$sub][$cvalue][$mainexMark->student_id] ? $stuoutof[$sub][$cvalue][$mainexMark->student_id] : 0;
                            $stutotmark = $stutot[$sub][$cvalue][$mainexMark->student_id] ? $stutot[$sub][$cvalue][$mainexMark->student_id] : 0;
                            $stutotout = $stuactoutof[$sub][$cvalue][$mainexMark->student_id] ? $stuactoutof[$sub][$cvalue][$mainexMark->student_id] : 0;
                            $stutot[$sub][$cvalue][$mainexMark->student_id] = $stutotmark + $nmark;
                            $stuoutof[$sub][$cvalue][$mainexMark->student_id] = $stuuniout + 1;
                            $stuactoutof[$sub][$cvalue][$mainexMark->student_id] = $stutotout + $obtainedoutOf;
                            $stat = $status[$sub][$cvalue][$mainexMark->student_id] == 'fail' ? 1 : 0;
                            if (!$stat)
                                $status[$sub][$cvalue][$mainexMark->student_id] = ($actualper >= 40 ) ? 'pass' : 'fail';
                        }
                        $overalstuout ++;
                    }
                    if (count($stuoutof) > 0) {
                        foreach ($stutot[$sub][$cvalue] as $key => $stot) {
                            $min_val[$sub][$cvalue][$key]['mark'] = ($stot * $stuactoutof[$sub][$cvalue][$key]) / $stuoutof[$sub][$cvalue][$key];
                            $min_val[$sub][$cvalue][$key]['outof'] = $stuactoutof[$sub][$cvalue][$key];
                            $min_val[$sub][$cvalue][$key]['stuid'] = $key;

                            if ($status[$sub][$cvalue][$key] == 'pass') {
                                $min_val[$sub][$cvalue][$key]['pass'] = $status[$sub][$cvalue][$key];
                            } else {
                                $min_val[$sub][$cvalue][$key]['fail'] = $status[$sub][$cvalue][$key];
                            }
                        }
                    }
                    if ($overalstuout > 0) {
                        $percent = round($overalstutotalmarks / count($student), 2);
                    }
                    if ($percent > 0):
                        $j = !$arrdata['center'] ? ($j + $k) : $j;
                        $arrdata['type'] = 'pie';
                        $arrdata['size'] = 100;
                        $arrdata['center'] = [$j, null];
                        $arrdata['name'] = $sub_name->name;
                        $arrdata['dataLabels']['enabled'] = true;
                        $arrdata['dataLabels']['distance'] = -30;
                        $arrdata['dataLabels']['x'] = -10;
                        $arrdata['dataLabels']['y'] = 70;
                        $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $sub_name->name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                        if ($seriescounter == 0) {
                            $arrdata['showInLegend'] = true;
                            $seriescounter ++;
                        }
                        $colorval = round($cvalue % 10);
                        $data['color'] = $colors[$colorval];
                        $data['slicecounter'] = $slicecounter++;
                        $data['seriescounter'] = $seriescounter;
                        $data['name'] = $nodename->name;
                        $data['y'] = $percent;
                        $data['sub_idt'] = $sub_name->id;
                        $data['sub_count'] = count($subjagg) > 1 ? 1 : 0;
                        $arrdata['data'][] = $data;
                    endif;
                    $compare = $min_val;
                }
            }
            if (count($arrdata) > 0) {
                $maindata[] = $arrdata;
            }
            $k = 200;
        }

        $manex = Mainexam ::findFirst('id=' . $params['exam_id']);
        $this->view->items = $maindata ? (str_replace(array('"%', '%"', '\r\n'), '', json_encode($maindata))) : '';
        $this->view->exam_name = $manex->exam_name;
        $this->view->node_id = $params['node_id'];
        $this->view->subject_id = $params['subject_id'];
        $this->view->type = $params['type'];
        $this->view->exam_id = $params['exam_id'];
        $this->view->compare = $compare;
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
    }

    public function getCmprsnChartListPieAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $mainexam = $data = array();
        if ($this->request->isPost()) {
            foreach ($this->request->getPost() as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv[0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                    $params['subjaggregate'][] = $value;
                } else {
                    $params[$key] = $value;
                }
            }
            $i = 0;
            $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
            if ($params['student_list']) {
                $students = explode(',', $params['student_list']);
                foreach ($students as $stud) {
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $res = ControllerBase::buildExamQuery(implode(',', $params['aggregateids']));
                    $mainexamdet = Mainexam ::find(implode(' or ', $res));
                    $seriesval = array();
                    $overallexmcut = $studentexamcnt = 0;
                    foreach ($mainexamdet as $mainex) {
                        $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                        $subj_Ids = array();
                        $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                        $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                        $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                        foreach ($subjectsid as $svalue) {
                            $subj_Ids[] = $svalue->subject_id;
                        }
                        $subj_Ids = array_unique($subj_Ids);
                        $overalclsout = $studentpercentforchart = 0;
                        foreach ($subj_Ids as $sub) {
                            $suject = array();
                            $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                            //  $suject = end($subjagg);
                            $suject = $this->find_childtreevaljson($sub);
                            $cnt = 0;
                            // $cnt = count(explode(',', $suject));
                            $cnt = count($suject);
                            $subject = explode(',', $suject);
                            $overalstuout = $overalstutotalmarks = 0;
                            $mainexamMarks = MainexamMarks::find('mainexam_id = ' . $mainex->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and subject_id IN ( ' . implode(',', $subjagg) . ')');
                            foreach ($mainexamMarks as $mainexMark) {
                                $exmname = Mainexam::findFirstById($mainexMark->mainexam_id);
                                $mainexam[$mainexMark->mainexam_id] = $exmname->exam_name;
                                $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                                $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                                if (($obtainedoutOf > 0)) {
                                    $studentpercentforchart += ($obtainedmark / $obtainedoutOf * 100);
                                }
                            }
                            $overalclsout += $cnt;
                        }
                        if ($overalclsout > 0) {
                            $studentexamcnt += round($studentpercentforchart / $overalclsout, 2);
                        }
                        $overallexmcut ++;
                    }

                    if ($overallexmcut > 0) {
                        $seriesval[] = round($studentexamcnt / $overallexmcut, 2);
                    }
                    if ($params['type'] == 'pie'):
                        if ($seriesval[0] != 0) {
                            $maindata['type'] = 'pie';
                            $maindata['name'] = 'MainExam';
                            $colorval = round($stud % 10);
                            $data['color'] = $colors[$colorval];
                            $data['name'] = $stud_name;
                            $data['y'] = array_shift($seriesval);
                            $maindata['data'][] = $data;
                        }
                    endif;
                }
            }
            if ($params['compareclassroom'][0]) {
                $compareclassroom = explode(',', $params['compareclassroom'][0]);
                foreach ($compareclassroom as $cvalue) {
                    $subjrr = array();
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                    foreach ($submaster as $ssvalue) {
                        $subjrr[] = $ssvalue->id;
                    }
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $students = $this->modelsManager->executeQuery($stuquery);
                    $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                    $subj_Ids = array();
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                    $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                    foreach ($subjectsid as $svalue) {
                        $subj_Ids[] = $svalue->subject_id;
                    }
                    $subj_Ids = array_unique($subj_Ids);
                    $name = array();
                    $name[] = $nodename->name;
                    $overallsubcnt = $percnt = 0;
                    $seriesva = array();
                    $series = 0;
                    $res = ControllerBase::buildExamQuery(implode(',', $params['aggregateids']));
                    $exam_arr = Mainexam ::find(implode(' or ', $res));
                    foreach ($exam_arr as $exm) {
                        $overalclsout = $studentpercentforchart = 0;
                        foreach ($subj_Ids as $sub) {
                            $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                            // $sject = end($subjagg);
                            $sject = $this->find_childtreevaljson($sub);
                            $cnt = 0;
                            // $cnt = count(explode(',', $sject));
                            $cnt = count($sject);
                            $sub_det = explode(',', $sject);
                            $mainexamMarks = MainexamMarks::find('mainexam_id =' . $exm->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subject_id IN ( ' . implode(',', $subjagg) . ')');
                            $overalstuout = $overalstutotalmarks = 0;
                            foreach ($mainexamMarks as $mainexMark) {
                                $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                                $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                                if (($obtainedoutOf > 0)) {
                                    $studentpercentforchart += ($obtainedmark / $obtainedoutOf * 100);
                                }
                            }
                            $overalclsout += $cnt;
                        }

                        if ($overalclsout > 0) {
                            $percnt += round($studentpercentforchart / $overalclsout, 2);
                        }

                        $overallsubcnt ++;
                    }
                    if ($overallsubcnt > 0) {
                        $series = round($percnt / $overallsubcnt, 2);
                        $seriesva[] = round($series / count($students), 2);
                    }
                    if ($params['type'] == 'pie'):
                        if ($seriesva[0] != 0) {
                            $maindata['type'] = 'pie';
                            $maindata['name'] = 'MainExam';
                            $colorval = round($cvalue % 10);
                            $data['color'] = $colors[$colorval];
                            $data['name'] = $name;
                            $data['y'] = array_shift($seriesva);
                            $maindata['data'][] = $data;
                        }
                    endif;
                }
            }
            if ($params['type'] == 'pie'):
                $this->view->items = $maindata ? json_encode($maindata) : '';
            endif;
            $this->view->xaxis = 'MainExam';
            $this->view->node_id = implode(',', $params['aggregateids']);
            $this->view->type = $params['type'];
            $this->view->student = $params['student_list'] ? $params['student_list'] : '';
            $this->view->classroom = $params['compareclassroom'][0] ? $params['compareclassroom'][0] : '';
            $this->view->name = $name = ControllerBase::getNameForKeys(implode(',', $params['aggregateids']));
        }
    }

    public function getClasstestChartPieAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $mainexam = array();
        if ($this->request->isPost()) {
            foreach ($this->request->getPost() as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv[0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                    $params['subjaggregate'][] = $value;
                } else {
                    $params[$key] = $value;
                }
            }

            $i = 0;
            $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
            if ($params['student_list']) {
                $students = explode(',', $params['student_list']);
                foreach ($students as $stud) {
                    $seriesval = array();
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subj_Ids = array();
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                    foreach ($subjectsid as $svalue) {
                        $subj_Ids[] = $svalue->subject_id;
                    }
                    $subj_Ids = array_unique($subj_Ids);
                    $overalsubout = $studentpercentforchart = 0;
                    foreach ($subj_Ids as $sub) {
                        $suject = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $overalstuout = $overalstutotalmarks = 0;
                        $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                        if (count($classtests) > 0) {
                            foreach ($classtests as $classtest) {
                                $clsTstMarks = ClassTestMarks::findFirst('class_test_id = ' . $classtest->class_test_id . ' and student_id = ' . $stud);
                                $obtainedmark = ($clsTstMarks->marks) ? $clsTstMarks->marks : 0;
                                $obtainedOutOf = ($clsTstMarks->outof) ? $clsTstMarks->outof : 0;
                                if (($obtainedOutOf) > 0) {
                                    $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                }
                                $overalstuout ++;
                            }
                            if ($overalstuout > 0) {
                                $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            }

                            $overalsubout ++;
                        }
                    }

                    if ($overalsubout > 0) {
                        $seriesval[] = round($studentpercentforchart / $overalsubout, 2);
                    }
                    if ($params['type'] == 'pie'):
                        if ($seriesval[0] != 0) {
                            $maindata['type'] = 'pie';
                            $maindata['name'] = 'ClassTest';
                            $data['color'] = $colors[$i++];
                            $data['name'] = $stud_name;
                            $data['y'] = array_shift($seriesval);
                            $data['myData'] = 'ClassTest';
                            $maindata['data'][] = $data;
                        }
                    endif;
                }
            }

            if ($params['compareclassroom'][0]) {
                $compareclassroom = explode(',', $params['compareclassroom'][0]);
                foreach ($compareclassroom as $cvalue) {
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $students = $this->modelsManager->executeQuery($stuquery);
                    $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                    $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                    foreach ($subjectsid as $svalue) {
                        $subj_Ids[] = $svalue->subject_id;
                    }
                    $subj_Ids = array_unique($subj_Ids);
                    $name = array();
                    $name[] = $nodename->name;
                    $seriesval = 0;
                    $totalarray = array();
                    $overalsubout = $studentpercentforchart = 0;
                    foreach ($subj_Ids as $sub) {
                        $suject = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $overalstuout = $overalstutotalmarks = 0;
                        $classtests = ClassTest::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                        if (count($classtests)) {
                            foreach ($classtests as $classtest) {
                                $clsTstMarks = ClassTestMarks::find('class_test_id = ' . $classtest->class_test_id);
                                foreach ($clsTstMarks as $clsmark) {
                                    $obtainedmark = ($clsmark->marks) ? $clsmark->marks : 0;
                                    $obtainedOutOf = ($clsmark->outof) ? $clsmark->outof : 0;
                                    if (($obtainedOutOf) > 0) {
                                        $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                    }
                                }

                                $overalstuout ++;
                            }
                            if ($overalstuout > 0) {
                                $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            }
                            $overalsubout ++;
                        }
                    }
                    if ($overalsubout > 0) {
                        $seriesval += round($studentpercentforchart / $overalsubout, 2);
                        $totalarray[] = round($seriesval / count($students), 2);
                    }

                    if ($params['type'] == 'pie'):
                        if ($totalarray[0] != 0) {
                            $maindata['type'] = 'pie';
                            $maindata['name'] = 'ClassTest';
                            $data['color'] = $colors[$i++];
                            $data['name'] = $name;
                            $data['y'] = array_shift($totalarray);
                            $data['myData'] = 'ClassTest';
                            $maindata['data'][] = $data;
                        }
                    endif;
                }
            }
            if ($params['type'] == 'pie'):
                $this->view->items = $maindata ? json_encode($maindata) : '';
            endif;
            $this->view->type = $params['type'];
            $this->view->xaxis = 'ClassTest';
            $this->view->node_id = implode(',', $params['aggregateids']);
            $this->view->student = $params['student_list'] ? $params['student_list'] : '';
            $this->view->classroom = $params['compareclassroom'][0] ? $params['compareclassroom'][0] : '';
            $this->view->name = $name = ControllerBase::getNameForKeys(implode(',', $params['aggregateids']));
        }
    }

    public function getAssignmentChartPieAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        $params = $mainexam = array();
        if ($this->request->isPost()) {
            foreach ($this->request->getPost() as $key => $value) {
                $IsSubdiv = explode('_', $key);
                if ($IsSubdiv[0] == 'aggregate' && $value) {
                    $params['aggregateids'][] = $value;
                } else if ($IsSubdiv[0] == 'subjaggregate' && $value) {
                    $params['subjaggregate'][] = $value;
                } else {
                    $params[$key] = $value;
                }
            }
            $i = 0;
            $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
            if ($params['student_list']) {
                $students = explode(',', $params['student_list']);
                foreach ($students as $stud) {
                    $seriesval = array();
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subj_Ids = array();
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                    foreach ($subjectsid as $svalue) {
                        $subj_Ids[] = $svalue->subject_id;
                    }
                    $subj_Ids = array_unique($subj_Ids);
                    $overalsubout = $studentpercentforchart = 0;
                    foreach ($subj_Ids as $sub) {
                        $suject = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $overalstuout = $overalstutotalmarks = 0;
                        $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                        if (count($assignments)) {
                            foreach ($assignments as $assign) {
                                $assignMarks = AssignmentMarks::findFirst('assignment_id = ' . $assign->id . ' and student_id = ' . $stud);
                                $obtainedmark = ($assignMarks->marks) ? $assignMarks->marks : 0;
                                $obtainedOutOf = ($assignMarks->outof) ? $assignMarks->outof : 0;
                                if (($obtainedOutOf) > 0) {
                                    $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                }
                                $overalstuout ++;
                            }
                            if ($overalstuout > 0) {
                                $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            }
                            $overalsubout ++;
                        }
                    }

                    if ($overalsubout > 0) {
                        $seriesval[] = round($studentpercentforchart / $overalsubout, 2);
                    }
                    if ($params['type'] == 'pie'):
                        if ($seriesval[0] != 0) {
                            $maindata['type'] = 'pie';
                            $maindata['name'] = 'Assignment';
                            $data['color'] = $colors[$i++];
                            $data['name'] = $stud_name;
                            $data['y'] = array_shift($seriesval);
                            $maindata['data'][] = $data;
                        }
                    endif;
                }
            }

            if ($params['compareclassroom'][0]) {
                $compareclassroom = explode(',', $params['compareclassroom'][0]);
                foreach ($compareclassroom as $cvalue) {
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $students = $this->modelsManager->executeQuery($stuquery);
                    $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                    $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                    foreach ($subjectsid as $svalue) {
                        $subj_Ids[] = $svalue->subject_id;
                    }
                    $subj_Ids = array_unique($subj_Ids);
                    $name = array();
                    $name[] = $nodename->name;
                    $seriesval = 0;
                    $totalarray = array();
                    $overalsubout = $studentpercentforchart = 0;
                    foreach ($subj_Ids as $sub) {
                        $suject = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $overalstuout = $overalstutotalmarks = 0;
                        $assignments = AssignmentsMaster::find('grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and subjct_modules IN ( ' . implode(',', $subjagg) . ')');
                        if (count($assignments)) {
                            foreach ($assignments as $assign) {
                                $assignMarks = AssignmentMarks::find('assignment_id = ' . $assign->id);
                                foreach ($assignMarks as $ass_marks) {
                                    $obtainedmark = ($ass_marks->marks) ? $ass_marks->marks : 0;
                                    $obtainedOutOf = ($ass_marks->outof) ? $ass_marks->outof : 0;
                                    if (($obtainedOutOf) > 0) {
                                        $overalstutotalmarks += ($obtainedmark / $obtainedOutOf * 100);
                                    }
                                }

                                $overalstuout ++;
                            }
                            if ($overalstuout > 0) {
                                $studentpercentforchart += round($overalstutotalmarks / $overalstuout, 2);
                            }
                            $overalsubout ++;
                        }
                    }
                    if ($overalsubout > 0) {
                        $seriesval += round($studentpercentforchart / $overalsubout, 2);
                        $totalarray[] = round($seriesval / count($students), 2);
                    }
                    if ($params['type'] == 'pie') {
                        if ($totalarray[0] != 0) {
                            $maindata['type'] = 'pie';
                            $maindata['name'] = 'Assignment';
                            $data['color'] = $colors[$i++];
                            $data['name'] = $name;
                            $data['y'] = array_shift($totalarray);
                            $maindata['data'][] = $data;
                        }
                    }
                }
            }

            if ($params['type'] == 'pie') {
                $this->view->items = $maindata ? json_encode($maindata) : '';
            }
            $this->view->xaxis = 'Assignment';
            $this->view->node_id = implode(',', $params['aggregateids']);
            $this->view->type = $params['type'];
            $this->view->student = $params['student_list'] ? $params['student_list'] : '';
            $this->view->classroom = $params['compareclassroom'][0] ? $params['compareclassroom'][0] : '';
            $this->view->name = $name = ControllerBase::getNameForKeys(implode(',', $params['aggregateids']));
        }
    }

    public function displayIndividualMainChartAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $i = 0;
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $this->view->sub = $sub = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id'])->name;
        $res = ControllerBase::buildExamQuery($params['node_id']);
        $mainexamdet = Mainexam ::find(implode(' or ', $res));
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                $seriesval = array();
                foreach ($mainexamdet as $mainex) {
                    $percent = $overalclsout = 0;
                    $subj_Ids = array();
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $overalstuout = $overalstutotalmarks = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $mainex->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and subject_id =' . $params['subject_id']);
                    if (count($mainexamMarks) > 0) {
                        foreach ($mainexamMarks as $mainexMark) {
                            $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                            $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                            if (($obtainedoutOf > 0)) {
                                $percent += ($obtainedmark / $obtainedoutOf * 100);
                            }
                            $overalclsout ++;
                        }
                        if ($overalclsout > 0) {
                            $seriesval[] = round($percent / $overalclsout, 2);
                        }
                        $mainexam[] = '<tspan >' . $mainex->exam_name . '<span style="display:none;">?' . $mainex->id . '</span></tspan>';
                    }
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $colorval = round($stud % 10);
                    $data['color'] = $colors[$colorval];
                    $data['name'] = $stud_name;
                    $data['data'] = array_values($seriesval);
                    $maindata[] = $data;
                }
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $subjrr = array();
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $stucount = explode('-', $nodename->aggregated_nodes_id);
                $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                        . 'stumap.subordinate_key,stumap.status'
                        . ' FROM StudentMapping stumap LEFT JOIN'
                        . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                        . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                $students = $this->modelsManager->executeQuery($stuquery);

                $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                foreach ($submaster as $ssvalue) {
                    $subjrr[] = $ssvalue->id;
                }
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $subj_Ids = array();
                $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $seriesval = array();
                $series = 0;
                foreach ($mainexamdet as $mainex) {
                    $main_val = array();
                    $percent = $overalclsout = 0;
                    $overalstuout = $overalstutotalmarks = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $mainex->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subject_id =' . $params['subject_id']);
                    if (count($mainexamMarks) > 0) {
                        foreach ($mainexamMarks as $mainexMark) {
                            $main_val[$cvalue][$mainex->id][$mainexMark->student_id] += (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                            $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                            $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                            if (($obtainedoutOf > 0)) {
                                $percent += ($obtainedmark / $obtainedoutOf * 100);
                            }
                            $overalclsout ++;
                        }
                        if ($overalclsout > 0) {
//                        $series = round($percent / $overalclsout, 2);
                            $seriesval[] = round($percent / count($students), 2);
                        }
                        $mainexam[] = '<tspan >' . $mainex->exam_name . '<span style="display:none;">?' . $mainex->id . '</span></tspan>';
                        $compare[] = $main_val;
                    }
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $colorval = round($cvalue % 10);
                    $data['color'] = $colors[$colorval];
                    $data['name'] = $nodename->name;
                    $data['data'] = array_values($seriesval);
                    $maindata[] = $data;
                }
            }
        }
        $this->view->items = $maindata ? json_encode($maindata) : '';
        $arryval = array_values($mainexam);
        $this->view->mainexam = json_encode($arryval);
        $this->view->node_id = $params['node_id'];
        $this->view->type = $params['type'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
        $this->view->compare = $compare;
    }

    public function displayIndividualMainChartPieAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $i = 0;
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $this->view->sub = $sub = OrganizationalStructureValues::findFirst('id = ' . $params['subject_id'])->name;
        $res = ControllerBase::buildExamQuery($params['node_id']);
        $mainexamdet = Mainexam ::find(implode(' or ', $res));
        $seriescounter = 0;
        $k = $a = 100;
        foreach ($mainexamdet as $mainex) {
            $i = $slicecounter = 0;
            $arrdata = array();
            if ($params['student']) {
                $students = explode(',', $params['student']);
                foreach ($students as $stud) {
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $percent = $overalclsout = $seriesval = 0;
                    $subj_Ids = array();
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $overalstuout = $overalstutotalmarks = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $mainex->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and subject_id =' . $params['subject_id']);
                    foreach ($mainexamMarks as $mainexMark) {
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $percent += ($obtainedmark / $obtainedoutOf * 100);
                        }
                        $overalclsout ++;
                    }
                    if ($overalclsout > 0) {
                        $seriesval = round($percent / $overalclsout, 2);
                    }
                    if ($seriesval > 0):
                        $j = !$arrdata['center'] ? ($j + $k) : $j;
                        $arrdata['type'] = 'pie';
                        $arrdata['size'] = 100;
                        $arrdata['center'] = [$j, null];
                        $arrdata['name'] = $mainex->exam_name;
                        $arrdata['dataLabels']['enabled'] = true;
                        $arrdata['dataLabels']['distance'] = -30;
                        $arrdata['dataLabels']['x'] = -10;
                        $arrdata['dataLabels']['y'] = 70;
                        $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $mainex->exam_name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                        if ($seriescounter == 0) {
                            $arrdata['showInLegend'] = true;
                        }
                        $colorval = round($stud % 10);
                        $data['color'] = $colors[$colorval];
                        $data['slicecounter'] = $slicecounter++;
                        $data['name'] = $stud_name;
                        $data['y'] = $seriesval;
                        $data['exam_id'] = $mainex->id;
                        $arrdata['data'][] = $data;
                    endif;
                }
            }
            if ($params['classroom']) {
                $compareclassroom = explode(',', $params['classroom']);
                foreach ($compareclassroom as $cvalue) {
                    $subjrr = array();
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $students = $this->modelsManager->executeQuery($stuquery);

                    $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                    foreach ($submaster as $ssvalue) {
                        $subjrr[] = $ssvalue->id;
                    }
                    $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                    $subj_Ids = array();
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                    $percent = $overalclsout = $seriesval = 0;
                    $overalstuout = $overalstutotalmarks = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $mainex->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subject_id =' . $params['subject_id']);
                    foreach ($mainexamMarks as $mainexMark) {
                        $main_val[$cvalue][$mainex->id][$mainexMark->student_id] += (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $percent += ($obtainedmark / $obtainedoutOf * 100);
                        }
                        $overalclsout ++;
                    }
                    if ($overalclsout > 0) {
                        $seriesval = round($percent / count($students), 2);
                    }
                    if ($seriesval > 0):
                        $j = !$arrdata['center'] ? ($j + $k) : $j;
                        $arrdata['type'] = 'pie';
                        $arrdata['size'] = 100;
                        $arrdata['center'] = [$j, null];
                        $arrdata['name'] = $mainex->exam_name;
                        $arrdata['dataLabels']['enabled'] = true;
                        $arrdata['dataLabels']['distance'] = -30;
                        $arrdata['dataLabels']['x'] = -10;
                        $arrdata['dataLabels']['y'] = 70;
                        $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $mainex->exam_name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                        if ($seriescounter == 0) {
                            $arrdata['showInLegend'] = true;
                        }
                        $colorval = round($cvalue % 10);
                        $data['color'] = $colors[$colorval];
                        $data['slicecounter'] = $slicecounter++;
                        $data['name'] = $nodename->name;
                        $data['y'] = $seriesval;
                        $data['exam_id'] = $mainex->id;
                        $arrdata['data'][] = $data;
                    endif;
                }
            }
            if (count($arrdata) > 0) {
                $maindata[] = $arrdata;
            }
            $seriescounter++;
            $k = 200;
        }
        $this->view->items = $maindata ? (str_replace(array('"%', '%"', '\r\n'), '', json_encode($maindata))) : '';
        $this->view->node_id = $params['node_id'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
    }

    public function examwiseCmprsnChartPrintAction() {
        $this->view->setTemplateAfter('printTemplates');
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $result = $this->MainExamChartFn($params);
        $this->view->node_id = $params['node_id'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
        $this->view->compare = $result['compare'];
    }

    public function MainExamChartFn($params) {
        $i = $j = 0;
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $subj_Ids = array();
        $arr = array();
        $res = ControllerBase::buildExamQuery($params['node_id']);
        $mainexamdet = Mainexam ::find(implode(' or ', $res));
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
            }
        }
        $subj_Ids = array_unique($subj_Ids);
        $cnt = count($subj_Ids);

        $min_val = array();
        $k = 100;
        $a = 200;
        $seriescounter = 0;
        foreach ($mainexamdet as $mainex) {
            $i = $slicecounter = 0;
            $arrdata = array();
            $j = $j + $k;
            $k = 200;
            if ($params['student']) {
                $students = explode(',', $params['student']);
                foreach ($students as $stud) {
                    $subjids = array();
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $percent = $overalclsout = $seriesval = 0;
                    foreach ($subj_Ids as $sub) {
                        $subjagg = $suject = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $suject = $this->find_childtreevaljson($sub);
                        $cnt = 0;
                        $cnt = count($suject);
                        $subject = explode(',', $suject);
                        $overalstuout = $overalstutotalmarks = 0;
                        $mainexamMarks = MainexamMarks::find('mainexam_id=' . $mainex->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and subject_id IN ( ' . implode(',', $subjagg) . ')');
                        foreach ($mainexamMarks as $mainexMark) {
                            $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                            $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                            if (($obtainedoutOf > 0)) {
                                $percent += ($obtainedmark / $obtainedoutOf * 100);
                            }
                        }
                        $overalclsout += $cnt;
                    }
                    if ($overalclsout > 0) {
                        $seriesval = round($percent / $overalclsout, 2);
                    }
                    if ($seriesval > 0):
                        $arrdata['type'] = 'pie';
                        $arrdata['size'] = 100;
                        $arrdata['center'] = [$j, null];
                        $arrdata['name'] = $mainex->exam_name;
                        $arrdata['startAngle'] = 90;
                        $arrdata['dataLabels']['enabled'] = true;
                        $arrdata['dataLabels']['distance'] = -30;
                        $arrdata['dataLabels']['x'] = -10;
                        $arrdata['dataLabels']['y'] = 70;
                        $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $mainex->exam_name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                        if ($seriescounter == 0) {
                            $arrdata['showInLegend'] = true;
                        }
                        $colorval = round($stud % 10);
                        $data['color'] = $colors[$colorval];
                        $data['slicecounter'] = $slicecounter++;
                        $data['name'] = $stud_name;
                        $data['y'] = $seriesval;
                        $data['exam_id'] = $mainex->id;
                        $arrdata['data'][] = $data;
                    endif;
                }
            }
            if ($params['classroom']) {
                $compareclassroom = explode(',', $params['classroom']);
                foreach ($compareclassroom as $cvalue) {
                    $subj_mas_Ids = array();
                    $stutot = $stuoutof = $stuactoutof = $status = array();
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                    foreach ($submaster as $ssvalue) {
                        $subj_mas_Ids[] = $ssvalue->id;
                    }
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $student = $this->modelsManager->executeQuery($stuquery);
                    $series = $seriesval = 0;
                    $percent = $overalclsout = 0;
                    foreach ($subj_Ids as $sub) {
                        $suject = $subjagg = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $suject = $this->find_childtreevaljson($sub);
                        $cnt = 0;
                        $cnt = count($suject);
                        $subject = explode(',', $suject);
                        $overalstuout = $overalstutotalmarks = 0;
                        $mainexamMarks = MainexamMarks::find('mainexam_id=' . $mainex->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subj_mas_Ids) . ') and subject_id IN ( ' . implode(',', $subjagg) . ')');
                        foreach ($mainexamMarks as $mainexMark) {
//                            $min_val[$mainex->id][$cvalue][$mainexMark->student_id] += (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                            $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                            $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                            if (($obtainedoutOf > 0)) {
                                $actualper = ($obtainedmark / $obtainedoutOf * 100);
                                $percent += $actualper;
                                $nmark = $obtainedmark / $obtainedoutOf;
                                $stuuniout = $stuoutof[$mainex->id][$cvalue][$mainexMark->student_id] ? $stuoutof[$mainex->id][$cvalue][$mainexMark->student_id] : 0;
                                $stutotmark = $stutot[$mainex->id][$cvalue][$mainexMark->student_id] ? $stutot[$mainex->id][$cvalue][$mainexMark->student_id] : 0;
                                $stutotout = $stuactoutof[$mainex->id][$cvalue][$mainexMark->student_id] ? $stuactoutof[$mainex->id][$cvalue][$mainexMark->student_id] : 0;
                                $stutot[$mainex->id][$cvalue][$mainexMark->student_id] = $stutotmark + $nmark;
                                $stuoutof[$mainex->id][$cvalue][$mainexMark->student_id] = $stuuniout + 1;
                                $stuactoutof[$mainex->id][$cvalue][$mainexMark->student_id] = $stutotout + $obtainedoutOf;
                                $stat = $status[$mainex->id][$cvalue][$mainexMark->student_id] == 'fail' ? 1 : 0;
                                if (!$stat)
                                    $status[$mainex->id][$cvalue][$mainexMark->student_id] = ($actualper >= 40 ) ? 'pass' : 'fail';
                            }
                        }
                        $overalclsout += $cnt;
                    }
                    if (count($stuoutof) > 0) {
                        foreach ($stutot[$mainex->id][$cvalue] as $key => $stot) {
                            $min_val[$mainex->id][$cvalue][$key]['mark'] = ($stot * $stuactoutof[$mainex->id][$cvalue][$key]) / $stuoutof[$mainex->id][$cvalue][$key];
                            $min_val[$mainex->id][$cvalue][$key]['outof'] = $stuactoutof[$mainex->id][$cvalue][$key];
                            $min_val[$mainex->id][$cvalue][$key]['stuid'] = $key;
                            if ($status[$mainex->id][$cvalue][$key] == 'pass') {
                                $min_val[$mainex->id][$cvalue][$key]['pass'] = $status[$mainex->id][$cvalue][$key];
                            } else {
                                $min_val[$mainex->id][$cvalue][$key]['fail'] = $status[$mainex->id][$cvalue][$key];
                            }
                        }
                    }
                    if ($overalclsout > 0) {
                        $series = round($percent / $overalclsout, 2);
                        $seriesval = round($series / count($student), 2);
                    }

                    if ($seriesval > 0):
                        $arrdata['type'] = 'pie';
                        $arrdata['size'] = 100;
                        $arrdata['center'] = [$j, null];
                        $arrdata['name'] = $mainex->exam_name;
                        $arrdata['startAngle'] = 90;
                        $arrdata['dataLabels']['enabled'] = true;
                        $arrdata['dataLabels']['distance'] = -30;
                        $arrdata['dataLabels']['x'] = -10;
                        $arrdata['dataLabels']['y'] = 70;
                        $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $mainex->exam_name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                        if ($seriescounter == 0) {
                            $arrdata['showInLegend'] = true;
                        }
                        $colorval = round($cvalue % 10);
                        $data['color'] = $colors[$colorval];
                        $data['slicecounter'] = $slicecounter++;
                        $data['name'] = $nodename->name;
                        $data['y'] = $seriesval;
                        $data['exam_id'] = $mainex->id;
                        $arrdata['data'][] = $data;
                    endif;
                    $compare = $min_val;
                }
            }
            if (count($arrdata) > 0) {
                $maindata[] = $arrdata;
            }
            $seriescounter++;
        }
        $arr['maindata'] = $maindata;
        $arr['compare'] = $compare;
        return $arr;
    }

    public function subwiseCmprsnChartPrintAction() {
        $this->view->setTemplateAfter('printTemplates');
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $i = $j = 0;
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $subj_Ids = array();
        $res = ControllerBase::buildExamQuery($params['node_id']);
        $mainexamdet = Mainexam ::find(implode(' or ', $res));
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
                $subj_mas_Ids[] = implode(',', $subjids);
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
                $subj_mas_Ids[] = implode(',', $subjids);
            }
        }
        $subj_Ids = array_unique($subj_Ids);
        $k = 100;
        $a = 100;
        $min_val = array();
        $seriescounter = 0;
        foreach ($subj_Ids as $sub) {
            $i = 0;
            $arrdata = array();
            $suject = $subjagg = array();
            $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
            $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
            $suject = $this->find_childtreevaljson($sub);
            $cnt = 0;
            $cnt = count($suject);
            $subject = explode(',', $suject);
            $slicecounter = 0;
            if ($params['student']) {
                $students = explode(',', $params['student']);
                foreach ($students as $stud) {
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $subjids = array();
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $overalstuout = $overalstutotalmarks = $percent = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and  subject_id IN ( ' . implode(',', $subjagg) . ')');
                    foreach ($mainexamMarks as $mainexMark) {
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $overalstutotalmarks += ($obtainedmark / $obtainedoutOf * 100);
                        }
                        $overalstuout ++;
                    }

                    if ($overalstuout > 0) {
                        $percent = round($overalstutotalmarks / $cnt, 2);
                    }
                    if ($percent > 0):
                        $j = !$arrdata['center'] ? ($j + $k) : $j;
                        $arrdata['type'] = 'pie';
                        $arrdata['size'] = 100;
                        $arrdata['center'] = [$j, null];
                        $arrdata['name'] = $sub_name->name;
                        $arrdata['startAngle'] = 90;
                        $arrdata['dataLabels']['enabled'] = true;
                        $arrdata['dataLabels']['distance'] = -40;
                        $arrdata['dataLabels']['x'] = 0;
                        $arrdata['dataLabels']['y'] = 70;
                        $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $sub_name->name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                        if ($seriescounter == 0) {
                            $arrdata['showInLegend'] = true;
                        }
                        $colorval = round($stud % 10);
                        $data['color'] = $colors[$colorval];
                        $data['slicecounter'] = $slicecounter++;
                        $data['name'] = $stud_name;
                        $data['y'] = $percent;
                        $data['sub_idt'] = $sub_name->id;
                        $data['sub_count'] = count($subjagg) > 1 ? 1 : 0;
                        $arrdata['data'][] = $data;
                    endif;
                }
            }
            if ($params['classroom']) {
                $compareclassroom = explode(',', $params['classroom']);
                foreach ($compareclassroom as $cvalue) {
                    $subj_mas_Ids = array();
                    $stutot = $stuoutof = $stuactoutof = $status = array();
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                    foreach ($submaster as $ssvalue) {
                        $subj_mas_Ids[] = $ssvalue->id;
                    }
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $student = $this->modelsManager->executeQuery($stuquery);
                    $overalstuout = $overalstutotalmarks = $percent = $finalval = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subj_mas_Ids) . ') and subject_id IN ( ' . implode(',', $subjagg) . ')');
                    foreach ($mainexamMarks as $mainexMark) {
//                        $min_val[$sub][$cvalue][$mainexMark->student_id] += (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $actualper = ($obtainedmark / $obtainedoutOf * 100);
                            $overalstutotalmarks += $actualper;
                            $nmark = $obtainedmark / $obtainedoutOf;
                            $stuuniout = $stuoutof[$cvalue][$sub][$mainexMark->student_id] ? $stuoutof[$cvalue][$sub][$mainexMark->student_id] : 0;
                            $stutotmark = $stutot[$cvalue][$sub][$mainexMark->student_id] ? $stutot[$cvalue][$sub][$mainexMark->student_id] : 0;
                            $stutotout = $stuactoutof[$cvalue][$sub][$mainexMark->student_id] ? $stuactoutof[$cvalue][$sub][$mainexMark->student_id] : 0;
                            $stutot[$cvalue][$sub][$mainexMark->student_id] = $stutotmark + $nmark;
                            $stuoutof[$cvalue][$sub][$mainexMark->student_id] = $stuuniout + 1;
                            $stuactoutof[$cvalue][$sub][$mainexMark->student_id] = $stutotout + $obtainedoutOf;
                            $stat = $status[$cvalue][$sub][$mainexMark->student_id] == 'fail' ? 1 : 0;
                            if (!$stat)
                                $status[$cvalue][$sub][$mainexMark->student_id] = ($actualper >= 40 ) ? 'pass' : 'fail';
                        }
                        $overalstuout ++;
                    }

                    if (count($stuoutof) > 0) {
                        foreach ($stutot[$cvalue][$sub] as $key => $stot) {
                            $min_val[$cvalue][$sub][$key]['mark'] = ($stot * $stuactoutof[$cvalue][$sub][$key]) / $stuoutof[$cvalue][$sub][$key];
                            $min_val[$cvalue][$sub][$key]['outof'] = $stuactoutof[$cvalue][$sub][$key];
                            $min_val[$cvalue][$sub][$key]['stuid'] = $key;

                            if ($status[$cvalue][$sub][$key] == 'pass') {
                                $min_val[$cvalue][$sub][$key]['pass'] = $status[$cvalue][$sub][$key];
                            } else {
                                $min_val[$cvalue][$sub][$key]['fail'] = $status[$cvalue][$sub][$key];
                            }
                        }
                    }
                    if ($overalstuout > 0) {
                        $finalval = round($overalstutotalmarks / $cnt, 2);
                        $percent = round($finalval / count($student), 2);
                    }
                    if ($percent > 0):
                        $j = !$arrdata['center'] ? ($j + $k) : $j;
                        $arrdata['type'] = 'pie';
                        $arrdata['size'] = 100;
                        $arrdata['center'] = [$j, null];
                        $arrdata['name'] = $sub_name->name;
                        $arrdata['startAngle'] = 90;
                        $arrdata['dataLabels']['enabled'] = true;
                        $arrdata['dataLabels']['distance'] = -30;
                        $arrdata['dataLabels']['x'] = -10;
                        $arrdata['dataLabels']['y'] = 70;
                        $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $sub_name->name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                        if ($seriescounter == 0) {
                            $arrdata['showInLegend'] = true;
                        }
                        $colorval = round($cvalue % 10);
                        $data['color'] = $colors[$colorval];
                        $data['slicecounter'] = $slicecounter++;
                        $data['name'] = $nodename->name;
                        $data['y'] = $percent;
                        $data['sub_idt'] = $sub_name->id;
                        $data['sub_count'] = count($subjagg) > 1 ? 1 : 0;
                        $arrdata['data'][] = $data;
                    endif;
                    $compare = $min_val;
                }
            }
            if (count($arrdata) > 0) {
                $maindata[] = $arrdata;
            }
            $seriescounter ++;
            $k = 200;
        }

        $manex = Mainexam ::findFirst('id=' . $params['exam_id']);
        $this->view->exam_name = $manex->exam_name;
        $this->view->node_id = $params['node_id'];
        $this->view->type = $params['type'];
        $this->view->exam_id = $params['exam_id'];
        $this->view->compare = $compare;
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
    }

    public function examwiseAllPrintAction() {
        $this->view->setTemplateAfter('printTemplates');
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $result = $this->mainExamPrint($params);
        $this->view->items = $result['maindata'] ? (str_replace(array('"%', '%"', '\r\n'), '', json_encode($result['maindata']))) : '';
        $this->view->compare = $result['compare'];
        $arryval = array_values($result['mainexam']);
        $this->view->mainexam = json_encode($arryval);
        $this->view->node_id = $params['node_id'];
        $this->view->type = $params['type'];
        $this->view->student = $params['student'] ? $params['student'] : '';
        $this->view->classroom = $params['classroom'] ? $params['classroom'] : '';
    }

    public function mainExamPrint($params) {
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $i = 0;
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                $res = ControllerBase::buildExamQuery($params['node_id']);
                $mainexamdet = Mainexam ::find(implode(' or ', $res));
                $seriesval = array();
                foreach ($mainexamdet as $mainex) {
                    $percent = $overalclsout = 0;
                    $subj_Ids = array();
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                    foreach ($subjectsid as $svalue) {
                        $subj_Ids[] = $svalue->subject_id;
                    }
                    $subj_Ids = array_unique($subj_Ids);
                    foreach ($subj_Ids as $sub) {
                        $suject = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $suject = $this->find_childtreevaljson($sub);
                        //$suject = end($subjagg);
                        $cnt = 0;
                        //   $cnt = count(explode(',', $suject));
                        $cnt = count($suject);
                        $subject = explode(',', $suject);
                        $overalstuout = $overalstutotalmarks = 0;
                        $mainexamMarks = MainexamMarks::find('mainexam_id=' . $mainex->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and subject_id IN ( ' . implode(',', $subjagg) . ')');

                        foreach ($mainexamMarks as $mainexMark) {
                            $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                            $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                            if (($obtainedoutOf > 0)) {
                                $percent += ($obtainedmark / $obtainedoutOf * 100);
                            }
                        }
                        $overalclsout += $cnt;
                    }
                    if ($overalclsout > 0) {
                        $seriesval[] = round($percent / $overalclsout, 2);
                    }
                    $mainexam[] = '<tspan >' . $mainex->exam_name . '<span style="display:none;">?' . $mainex->id . '</span></tspan>';
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $stud_name;
                    $data['data'] = array_values($seriesval);
                    $maindata[] = $data;
                }
            }
        }
        if ($params['classroom']) {
            $min_val = array();
            $compareclassroom = explode(',', $params['classroom']);
            foreach ($compareclassroom as $cvalue) {
                $subjrr = array();
                $nodename = ClassroomMaster::findFirstById($cvalue);
                $stucount = explode('-', $nodename->aggregated_nodes_id);
                $res = ControllerBase::buildStudentQuery(implode(',', $stucount));

                $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name as name,stumap.aggregate_key,'
                        . 'stumap.subordinate_key,stumap.status'
                        . ' FROM StudentMapping stumap LEFT JOIN'
                        . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                        . ' (' . implode(' or ', $res) . ') ORDER BY name ASC';
                $students = $this->modelsManager->executeQuery($stuquery);

                $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                foreach ($submaster as $ssvalue) {
                    $subjrr[] = $ssvalue->id;
                }
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $subj_Ids = array();
                $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
                $subj_Ids = array_unique($subj_Ids);

                $name = array();
                $name[] = $nodename->name;
                $res = ControllerBase::buildExamQuery($params['node_id']);
                $mainexamdet = Mainexam ::find(implode(' or ', $res));
                $seriesval = array();
                $series = 0;
                foreach ($mainexamdet as $mainex) {
                    $percent = $overalclsout = 0;
                    $stutot = $stuoutof = $stuactoutof = $status = array();
                    foreach ($subj_Ids as $sub) {
                        $suject = array();
                        $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                        $suject = $this->find_childtreevaljson($sub);
                        $cnt = 0;
                        $cnt = count($suject);
                        $subject = explode(',', $suject);
                        $overalstuout = $overalstutotalmarks = 0;
                        $mainexamMarks = MainexamMarks::find('mainexam_id=' . $mainex->id . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subject_id IN ( ' . implode(',', $subjagg) . ')');
                        foreach ($mainexamMarks as $mainexMark) {
                            $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                            $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                            if (($obtainedoutOf > 0)) {
                                $actualper = ($obtainedmark / $obtainedoutOf * 100);
                                $percent += $actualper;
                                $nmark = $obtainedmark / $obtainedoutOf;
                                $stuuniout = $stuoutof[$mainex->id][$cvalue][$mainexMark->student_id] ? $stuoutof[$mainex->id][$cvalue][$mainexMark->student_id] : 0;
                                $stutotmark = $stutot[$mainex->id][$cvalue][$mainexMark->student_id] ? $stutot[$mainex->id][$cvalue][$mainexMark->student_id] : 0;
                                $stutotout = $stuactoutof[$mainex->id][$cvalue][$mainexMark->student_id] ? $stuactoutof[$mainex->id][$cvalue][$mainexMark->student_id] : 0;
                                $stutot[$mainex->id][$cvalue][$mainexMark->student_id] = $stutotmark + $nmark;
                                $stuoutof[$mainex->id][$cvalue][$mainexMark->student_id] = $stuuniout + 1;
                                $stuactoutof[$mainex->id][$cvalue][$mainexMark->student_id] = $stutotout + $obtainedoutOf;
                                $stat = $status[$mainex->id][$cvalue][$mainexMark->student_id] == 'fail' ? 1 : 0;
                                if (!$stat)
                                    $status[$mainex->id][$cvalue][$mainexMark->student_id] = ($actualper >= 40 ) ? 'pass' : 'fail';
                            }
                        }
                        $overalclsout += $cnt;
                    }
                    if (count($stuoutof) > 0) {
                        foreach ($stutot[$mainex->id][$cvalue] as $key => $stot) {
                            $min_val[$mainex->id][$cvalue][$key]['mark'] = ($stot * $stuactoutof[$mainex->id][$cvalue][$key]) / $stuoutof[$mainex->id][$cvalue][$key];
                            $min_val[$mainex->id][$cvalue][$key]['outof'] = $stuactoutof[$mainex->id][$cvalue][$key];
                            $min_val[$mainex->id][$cvalue][$key]['stuid'] = $key;
                            if ($status[$mainex->id][$cvalue][$key] == 'pass') {
                                $min_val[$mainex->id][$cvalue][$key]['pass'] = $status[$mainex->id][$cvalue][$key];
                            } else {
                                $min_val[$mainex->id][$cvalue][$key]['fail'] = $status[$mainex->id][$cvalue][$key];
                            }
                        }
                    }
                    if ($overalclsout > 0) {
                        $series = round($percent / $overalclsout, 2);
                        $seriesval[] = round($series / count($students), 2);
                    }
                    $mainexam[] = '<tspan >' . $mainex->exam_name . '<span style="display:none;">?' . $mainex->id . '</span></tspan>';
                    $compare = $min_val;
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $name;
                    $data['data'] = array_values($seriesval);
                    $maindata[] = $data;
                }
            }
        }
        $arr['mainexam'] = $mainexam;
        $arr['maindata'] = $maindata;
        $arr['compare'] = $compare;
        return $arr;
    }

    public function subwiseMainExamPrintAction() {
        $this->view->setTemplateAfter('printTemplates');
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $result = $this->mainExamSubj($params);
        $this->view->compare = $result['compare'];
        $manex = Mainexam ::findFirst('id=' . $params['exam_id']);
        $this->view->exam_name = $manex->exam_name;
    }

    public function mainExamSubj($params) {
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $i = 0;
        $min_val = array();
        if ($params['student']) {
            $students = explode(',', $params['student']);
            foreach ($students as $stud) {
                $percent = array();
                $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                $aggregate_key = array();
                $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                $subjpids = ControllerBase::getAlSubjChildNodes(explode(',', $aggregate_key));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
                $uniq_subid = array_unique($subj_Ids);
                $seriesval = array();
                $subject_name = array();
                foreach ($uniq_subid as $sub) {
                    $suject = array();
                    $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                    $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                    $suject = $this->find_childtreevaljson($sub);
                    $cnt = 0;
                    $cnt = count($suject);
                    $subject = explode(',', $suject);
                    $overalstuout = $overalstutotalmarks = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and  subject_id IN ( ' . implode(',', $subjagg) . ')');
                    foreach ($mainexamMarks as $mainexMark) {
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $overalstutotalmarks += ($obtainedmark / $obtainedoutOf * 100);
                        }
                        $overalstuout ++;
                    }

                    if ($overalstuout > 0) {
                        $percent[] = round($overalstutotalmarks / $cnt, 2);
                    } else {
                        $percent[] = 0;
                    }
                    $subject_name[] = count($subjagg) > 1 ? '<tspan style="color:red;text-decoration: none;cursor:pointer;" >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '?yes</span></tspan>' :
                            ' <tspan style="color:red;text-decoration: none;cursor:pointer;" >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '</span></tspan>';
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $stud_name;
                    $data['data'] = array_values($percent);
                    $maindata[] = $data;
                }
            }
        }
        if ($params['classroom']) {
            $compareclassroom = explode(',', $params['classroom']);

            foreach ($compareclassroom as $cvalue) {

                $nodename = ClassroomMaster::findFirstById($cvalue);
                $subjrr = array();
                $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                foreach ($submaster as $ssvalue) {
                    $subjrr[] = $ssvalue->id;
                }
                $stucount = explode('-', $nodename->aggregated_nodes_id);
                $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                        . 'stumap.subordinate_key,stumap.status'
                        . ' FROM StudentMapping stumap LEFT JOIN'
                        . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                        . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                $students = $this->modelsManager->executeQuery($stuquery);
                $cname = ControllerBase::getNameForKeys($nodename->aggregated_nodes_id);
                $subjpids = ControllerBase::getAlSubjChildNodes(explode('-', $nodename->aggregated_nodes_id));
                $subjids = ControllerBase::getGrpSubjMasPossiblities(explode('-', $nodename->aggregated_nodes_id));
                $subjectsid = GroupSubjectsTeachers::find('id IN (' . implode(',', $subjids) . ')');
                foreach ($subjectsid as $svalue) {
                    $subj_Ids[] = $svalue->subject_id;
                }
                $name = array();
                $name[] = $nodename->name;
                $percent = array();
                $finalval = 0;
                $subject_name = array();
                $uniq_subid = array_unique($subj_Ids);
                $stutot = $stuoutof = $stuactoutof = $status = array();
                foreach ($uniq_subid as $sub) {
                    $suject = array();
                    $subjagg = ControllerBase::getAllSubjectAndSubModules(array($sub));
                    $sub_name = OrganizationalStructureValues::findFirst('id = ' . $subjagg[0]);
                    $suject = $this->find_childtreevaljson($sub);
                    $cnt = 0;
                    $cnt = count($suject);
                    $subject = explode(',', $suject);
                    $overalstuout = $overalstutotalmarks = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjrr) . ') and subject_id IN ( ' . implode(',', $subjagg) . ')');

                    foreach ($mainexamMarks as $mainexMark) {
//                        $min_val[$sub][$cvalue][$mainexMark->student_id] += (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $actualper = ($obtainedmark / $obtainedoutOf * 100);
                            $overalstutotalmarks += $actualper;
                            $nmark = $obtainedmark / $obtainedoutOf;
                            $stuuniout = $stuoutof[$cvalue][$sub][$mainexMark->student_id] ? $stuoutof[$cvalue][$sub][$mainexMark->student_id] : 0;
                            $stutotmark = $stutot[$cvalue][$sub][$mainexMark->student_id] ? $stutot[$cvalue][$sub][$mainexMark->student_id] : 0;
                            $stutotout = $stuactoutof[$cvalue][$sub][$mainexMark->student_id] ? $stuactoutof[$cvalue][$sub][$mainexMark->student_id] : 0;
                            $stutot[$cvalue][$sub][$mainexMark->student_id] = $stutotmark + $nmark;
                            $stuoutof[$cvalue][$sub][$mainexMark->student_id] = $stuuniout + 1;
                            $stuactoutof[$cvalue][$sub][$mainexMark->student_id] = $stutotout + $obtainedoutOf;
                            $stat = $status[$cvalue][$sub][$mainexMark->student_id] == 'fail' ? 1 : 0;
                            if (!$stat)
                                $status[$cvalue][$sub][$mainexMark->student_id] = ($actualper >= 40 ) ? 'pass' : 'fail';
                        }
                        $overalstuout ++;
                    }

                    if (count($stuoutof) > 0) {
                        foreach ($stutot[$cvalue][$sub] as $key => $stot) {
                            $min_val[$cvalue][$sub][$key]['mark'] = ($stot * $stuactoutof[$cvalue][$sub][$key]) / $stuoutof[$cvalue][$sub][$key];
                            $min_val[$cvalue][$sub][$key]['outof'] = $stuactoutof[$cvalue][$sub][$key];
                            $min_val[$cvalue][$sub][$key]['stuid'] = $key;
                            if ($status[$cvalue][$sub][$key] == 'pass') {
                                $min_val[$cvalue][$sub][$key]['pass'] = $status[$cvalue][$sub][$key];
                            } else {
                                $min_val[$cvalue][$sub][$key]['fail'] = $status[$cvalue][$sub][$key];
                            }
                        }
                    }
                    if ($overalstuout > 0) {
                        $finalval = round($overalstutotalmarks / $cnt, 2);
                        $percent[] = round($finalval / count($students), 2);
                    } else {
                        $percent[] = 0;
                    }

                    $subject_name[] = count($subjagg) > 1 ? '<tspan style="color:red;text-decoration: none;cursor:pointer;" >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '?yes</span></tspan>' :
                            '<tspan style="color:red;text-decoration: none;cursor:pointer;" >' . $sub_name->name . '<span style="display:none;">?' . $sub_name->id . '</span></tspan>';
                    $compare = $min_val;
                }
                if ($params['type'] == 'column' || $params['type'] == 'bar' || $params['type'] == 'line') {
                    $data['data'] = '';
                    $data['type'] = $params['type'] == 'line' ? "spline" : $params['type'];
                    $data['color'] = $colors[$i++];
                    $data['name'] = $name;
                    $data['data'] = array_values($percent);
                    $maindata[] = $data;
                }
            }
        }
        $arr['compare'] = $compare;
        return $arr;
    }

    public function loadDailyAttnReportAction() {
        $this->view->setRenderLevel(View::LEVEL_ACTION_VIEW);
        if ($this->request->isPost()) {
            $this->view->masterid = $aggregateids = $gnparam['aggregateids'] = $this->request->getPost('aggregateids');
            $this->view->reptyp = $reptyp = $gnparam['reptyp'] = $this->request->getPost('reptyp');
            $this->view->month = $month = $gnparam['month'] = $this->request->getPost('month');
            $this->view->year = $year = $gnparam['year'] = $this->request->getPost('year');
            $rescal = $this->calculateAttendancePercentDaily($gnparam);
            $this->view->classStudents = $rescal[0];
            $this->view->dailyPercent = $rescal[1];
            $this->view->monthhead = $rescal[4];
            $this->view->valhead = $rescal[3];
            $this->view->legend = $rescal[2];
            $this->view->result = $rescal[5];
        }
    }

    public function calculateAttendancePercentDaily($gnparam) {
        $aggregateids = $gnparam['aggregateids'];
        $reptyp = $gnparam['reptyp'];
        $month = $gnparam['month'];
        $year = $gnparam['year'];
        $user_type = 'student';
        $newarr = array();
        $res = ControllerBase::buildStudentQuery($aggregateids);
        $stuquery = 'SELECT stumap.id,stumap.student_info_id,stumap.aggregate_key,'
                . 'stumap.subordinate_key,stumap.status,stuinfo.loginid,stuinfo.Date_of_Joining,'
                . ' stuinfo.Admission_no,stuinfo.photo,stuinfo.Student_Name '
                . ' FROM StudentMapping stumap LEFT JOIN'
                . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass"'
                . '  and (' . implode(' or ', $res)
                . ') ORDER BY stuinfo.Student_Name ASC';
        $classStudents = $this->modelsManager->executeQuery($stuquery);
        $dailyPercent = $result = array();
        if (count($classStudents) > 0):
            $params['monthhead'] = array();
            $params['valhead'] = array();
            foreach ($classStudents as $classStudent) {
                $overallPercent = array();
                $params['user_id'] = $classStudent->loginid;
                $params['student_id'] = $classStudent->student_info_id;
                $params['doj'] = $classStudent->Date_of_Joining;
                $params['user_type'] = 'student';
                $params['month'] = $month;
                $params['year'] = $year;
                $overallPercent = ReportsController::getAttendancePercentDaily($params);
                $dailyPercent = array_merge($dailyPercent, $overallPercent[0]);
//                    $params['monthhead'] = ($overallPercent[1]);
                $params['valhead'] = ($overallPercent[2]);
                $params['legend'] = ($overallPercent[3]);
            }
        endif;
        $i = $j = 0;

        $startdt = (date('Y-m-01 00:00:00', strtotime('01-' . $month . '-' . $year)));
        $enddt = (date('Y-m-01 00:00:00', strtotime('01-' . $month . '-' . $year . ' +1 month')));
        $begin = new DateTime($startdt);
        $end = new DateTime($enddt);
        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);
        foreach ($period as $dt) {
            $monthhead[] = strtotime($dt->format("Y-m-d H:i:s"));
        }
//            $monthhead = array_unique($params['monthhead']);
        $valhead = array_unique($params['valhead']);

        if ($reptyp == 1) {

            foreach ($classStudents as $classStudent) {
                $noofperiods = PeriodMaster::find('LOCATE(node_id,"' . str_replace(',', '-', $classStudent->aggregate_key) . '" )' . " and user_type = 'student'");
                if (count($monthhead) > 0) {
                    foreach ($monthhead as $hvalue) {
                        $atttaken = $stu_att_totByVal = 0;
                        if (count($valhead) > 0) {
                            foreach ($valhead as $vvalue) {
                                $student_att_props_val = AttendanceSelectbox::findFirst("attendance_for = '$user_type'"
                                                . ' and attendanceid= "' . $vvalue . '"');
                                $counttaken = $dailyPercent[$classStudent->loginid][$hvalue][$vvalue] ? ($dailyPercent[$classStudent->loginid][$hvalue][$vvalue] ) : 0;
                                $atttaken += ($counttaken / count($noofperiods));
                                $stu_att_totByVal += ($counttaken / count($noofperiods)) * $student_att_props_val->attendancevalue;
                            }
                        }
                        $dailyPercent[$classStudent->loginid][$hvalue]['percent'] = ($atttaken > 0) ? round(($stu_att_totByVal / ($atttaken * $noofperiods)) * 100, 2) : '';
                    }
                }
            }
            $legend = array();
            $result['thead'][$i][$j]['text'] = 'Students&nbsp;&nbsp;&nbsp;&nbsp;';
            $result['thead'][$i][$j]['rowspan'] = '';
            $result['thead'][$i][$j]['colspan'] = '';

            if (count($monthhead) > 0) {
                foreach ($monthhead as $hvalue) {
                    $j++;
                    $result['thead'][$i][$j]['text'] = date('D d', $hvalue);
                    $result['thead'][$i][$j]['rowspan'] = '';
                    $result['thead'][$i][$j]['colspan'] = '';
                }
                $j++;
                $result['thead'][$i][$j]['text'] = 'Total';
                $result['thead'][$i][$j]['rowspan'] = '';
                $result['thead'][$i][$j]['colspan'] = '';
            }
        } else {

            foreach ($classStudents as $classStudent) {
                $noofperiods = PeriodMaster::find('LOCATE(node_id,"' . str_replace(',', '-', $classStudent->aggregate_key) . '" )' . " and user_type = 'student'");
                if (count($monthhead) > 0) {
                    foreach ($monthhead as $hvalue) {
                        if (count($valhead) > 0) {
                            foreach ($valhead as $vvalue) {
                                $atttaken = $stu_att_totByVal = 0;
                                $counttaken = $dailyPercent[$classStudent->loginid][$hvalue][$vvalue] ? ($dailyPercent[$classStudent->loginid][$hvalue][$vvalue] ) : 0;
                                $atttaken += ($counttaken / count($noofperiods));
                                $dailyPercent[$classStudent->loginid][$hvalue][$vvalue] = ($atttaken > 0) ? $atttaken : '';
                            }
                        }
                    }
                }
            }
            $result['thead'][$i][$j]['text'] = 'Students&nbsp;&nbsp;&nbsp;&nbsp;';
            $result['thead'][$i][$j]['rowspan'] = '2';
            $result['thead'][$i][$j]['colspan'] = '';

            $legend = array_unique($params['legend']);
            if (count($monthhead) > 0) {
                foreach ($monthhead as $hvalue) {
                    $j++;
                    $result['thead'][$i][$j]['text'] = date('D d', $hvalue);
                    $result['thead'][$i][$j]['rowspan'] = '';
                    $result['thead'][$i][$j]['colspan'] = count($valhead);
                }
                $j++;
                $result['thead'][$i][$j]['text'] = 'Total';
                $result['thead'][$i][$j]['rowspan'] = '';
                $result['thead'][$i][$j]['colspan'] = count($valhead);
            }
            $i++;
            if (count($monthhead) > 0) {
                foreach ($monthhead as $hvalue) {
                    if (count($valhead) > 0) {
                        foreach ($valhead as $vvalue) {
                            $j++;
                            $result['thead'][$i][$j]['text'] = $vvalue;
                            $result['thead'][$i][$j]['rowspan'] = '';
                            $result['thead'][$i][$j]['colspan'] = '';
                        }
                    }
                }
                if (count($valhead) > 0) {
                    foreach ($valhead as $vvalue) {
                        $j++;
                        $result['thead'][$i][$j]['text'] = $vvalue;
                        $result['thead'][$i][$j]['rowspan'] = '';
                        $result['thead'][$i][$j]['colspan'] = '';
                    }
                }
            }
        }
        $newarr = array($classStudents, $dailyPercent, $legend, $valhead, $monthhead, $result);
        return $newarr;
    }

    public function studentMainExamMarkListAction() {
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $classroommas = ClassroomMaster::findFirstById($params['classroom_id']);
        $grpsubjids = GroupSubjectsTeachers::find('classroom_master_id = ' . $classroommas->id);
        $subj_Ids = $subjids = $finids = array();
        if (count($grpsubjids) > 0):
            foreach ($grpsubjids as $pusid) {
                $finids[] = $pusid->id;
                $subj_Ids[] = $pusid->subject_id;
            }
            $subjids = array_unique($finids);
        endif;
        $this->view->students = $params['student_id'] ? explode(',', $params['student_id']) : '';
        $this->view->masterid = $params['classroom_id'];
        $this->view->detail = $params['detail'];
        $this->view->subject_id = array_unique($subj_Ids);
        $this->view->mainExId = $params['exam_id'];
        $this->view->sub_mas_id = implode(',', $subjids);
        $this->view->classroomname = $classroommas->name;
        $this->view->exam = Mainexam ::findFirstById($params['exam_id']);
    }

    public function submoduleMainExamPrintPieAction() {
        $this->view->setTemplateAfter('printTemplates');
        $val = json_decode($this->request->getPost('params'));
        $params = array();
        foreach ($val as $key => $value) {
            $params[$key] = $value;
        }
        $i = $j = 0;
        $this->view->name = $name = ControllerBase::getNameForKeys($params['node_id']);
        $colors = array('#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92');
        $suject = $this->find_childtreevaljson($params['subject_id']);
        $k = 100;
        $a = 100;
        $min_val = array();
        $seriescounter = 0;
        foreach ($suject as $sub) {
            $i = 0;
            $arrdata = array();
            $sub_name = OrganizationalStructureValues::findFirst('id = ' . $sub);
            $slicecounter = 0;
            if ($params['student']) {
                $students = explode(',', $params['student']);
                foreach ($students as $stud) {
                    $stud_name = StudentInfo::findFirstById($stud)->Student_Name;
                    $aggregate_key = StudentMapping::findFirst('student_info_id =' . $stud)->aggregate_key;
                    $subjids = ControllerBase::getGrpSubjMasPossiblities(explode(',', $aggregate_key));
                    $overalstuout = $overalstutotalmarks = $percent = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subjids) . ') and student_id = ' . $stud . ' and  subject_id =' . $sub . ' ORDER BY subject_id');
                    foreach ($mainexamMarks as $mainexMark) {
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $overalstutotalmarks += ($obtainedmark / $obtainedoutOf * 100);
                        }
                        $overalstuout ++;
                    }

                    if ($overalstuout > 0) {
                        $percent = round($overalstutotalmarks / $cnt, 2);
                    }
                    if ($percent > 0):
                        $j = !$arrdata['center'] ? ($j + $k) : $j;
                        $arrdata['type'] = 'pie';
                        $arrdata['size'] = 100;
                        $arrdata['center'] = [$j, null];
                        $arrdata['name'] = $sub_name->name;
                        $arrdata['dataLabels']['enabled'] = true;
                        $arrdata['dataLabels']['distance'] = -40;
                        $arrdata['dataLabels']['x'] = 0;
                        $arrdata['dataLabels']['y'] = 70;
                        $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $sub_name->name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                        if ($seriescounter == 0) {
                            $arrdata['showInLegend'] = true;
                            $seriescounter ++;
                        }
                        $colorval = round($stud % 10);
                        $data['color'] = $colors[$colorval];
                        $data['slicecounter'] = $slicecounter++;
                        $data['seriescounter'] = $seriescounter;
                        $data['name'] = $stud_name;
                        $data['y'] = $percent;
                        $data['sub_idt'] = $sub_name->id;
                        $data['sub_count'] = count($subjagg) > 1 ? 1 : 0;
                        $arrdata['data'][] = $data;
                    endif;
                }
            }
            if ($params['classroom']) {
                $compareclassroom = explode(',', $params['classroom']);
                foreach ($compareclassroom as $cvalue) {
                    $subj_mas_Ids = array();
                    $stutot = $stuoutof = $stuactoutof = $status = array();
                    $nodename = ClassroomMaster::findFirstById($cvalue);
                    $submaster = GroupSubjectsTeachers::find('classroom_master_id =' . $cvalue);
                    foreach ($submaster as $ssvalue) {
                        $subj_mas_Ids[] = $ssvalue->id;
                    }
                    $stucount = explode('-', $nodename->aggregated_nodes_id);
                    $res = ControllerBase::buildStudentQuery(implode(',', $stucount));
                    $stuquery = 'SELECT stumap.id,stumap.student_info_id,stuinfo.Student_Name,stumap.aggregate_key,'
                            . 'stumap.subordinate_key,stumap.status'
                            . ' FROM StudentMapping stumap LEFT JOIN'
                            . ' StudentInfo stuinfo ON stuinfo.id=stumap.student_info_id WHERE stumap.status = "Inclass" and '
                            . ' (' . implode(' or ', $res) . ') ORDER BY stuinfo.Student_Name ASC';
                    $student = $this->modelsManager->executeQuery($stuquery);
                    $overalstuout = $overalstutotalmarks = $percent = $finalval = 0;
                    $mainexamMarks = MainexamMarks::find('mainexam_id=' . $params['exam_id'] . ' and grp_subject_teacher_id IN ( ' . implode(',', $subj_mas_Ids) . ') and subject_id =' . $sub . ' ORDER BY subject_id');

                    foreach ($mainexamMarks as $mainexMark) {
                        $obtainedmark = (($mainexMark->inherited_marks) ? $mainexMark->inherited_marks : 0 ) + (($mainexMark->marks) ? $mainexMark->marks : 0);
                        $obtainedoutOf = (($mainexMark->inherited_outof ) ? $mainexMark->inherited_outof : 0 ) + (($mainexMark->outof) ? $mainexMark->outof : 0);
                        if (($obtainedoutOf > 0)) {
                            $actualper = ($obtainedmark / $obtainedoutOf * 100);
                            $overalstutotalmarks += $actualper;
                            $nmark = $obtainedmark / $obtainedoutOf;
                            $stuuniout = $stuoutof[$cvalue][$sub][$mainexMark->student_id] ? $stuoutof[$cvalue][$sub][$mainexMark->student_id] : 0;
                            $stutotmark = $stutot[$cvalue][$sub][$mainexMark->student_id] ? $stutot[$cvalue][$sub][$mainexMark->student_id] : 0;
                            $stutotout = $stuactoutof[$cvalue][$sub][$mainexMark->student_id] ? $stuactoutof[$cvalue][$sub][$mainexMark->student_id] : 0;
                            $stutot[$cvalue][$sub][$mainexMark->student_id] = $stutotmark + $nmark;
                            $stuoutof[$cvalue][$sub][$mainexMark->student_id] = $stuuniout + 1;
                            $stuactoutof[$cvalue][$sub][$mainexMark->student_id] = $stutotout + $obtainedoutOf;
                            $stat = $status[$cvalue][$sub][$mainexMark->student_id] == 'fail' ? 1 : 0;
                            if (!$stat)
                                $status[$cvalue][$sub][$mainexMark->student_id] = ($actualper >= 40 ) ? 'pass' : 'fail';
                        }
                        $overalstuout ++;
                    }
                    if (count($stuoutof) > 0) {
                        foreach ($stutot[$cvalue][$sub] as $key => $stot) {
                            $min_val[$cvalue][$sub][$key]['mark'] = ($stot * $stuactoutof[$cvalue][$sub][$key]) / $stuoutof[$cvalue][$sub][$key];
                            $min_val[$cvalue][$sub][$key]['outof'] = $stuactoutof[$cvalue][$sub][$key];
                            $min_val[$cvalue][$sub][$key]['stuid'] = $key;

                            if ($status[$cvalue][$sub][$key] == 'pass') {
                                $min_val[$cvalue][$sub][$key]['pass'] = $status[$cvalue][$sub][$key];
                            } else {
                                $min_val[$cvalue][$sub][$key]['fail'] = $status[$cvalue][$sub][$key];
                            }
                        }
                    }
                    if ($overalstuout > 0) {
                        $percent = round($overalstutotalmarks / count($student), 2);
                    }

                    if ($percent > 0):
                        $j = !$arrdata['center'] ? ($j + $k) : $j;
                        $arrdata['type'] = 'pie';
                        $arrdata['size'] = 100;
                        $arrdata['center'] = [$j, null];
                        $arrdata['name'] = $sub_name->name;
                        $arrdata['dataLabels']['enabled'] = true;
                        $arrdata['dataLabels']['distance'] = -30;
                        $arrdata['dataLabels']['x'] = -10;
                        $arrdata['dataLabels']['y'] = 70;
                        $arrdata['dataLabels']['formatter'] = '%function(){
                            if(this.point.slicecounter ==0){
                                    return \'' . $sub_name->name . '\';
                                        }else{
                                        return \'\';
                                        }
                                }%';
                        if ($seriescounter == 0) {
                            $arrdata['showInLegend'] = true;
                            $seriescounter ++;
                        }
                        $colorval = round($cvalue % 10);
                        $data['color'] = $colors[$colorval];
                        $data['slicecounter'] = $slicecounter++;
                        $data['seriescounter'] = $seriescounter;
                        $data['name'] = $nodename->name;
                        $data['y'] = $percent;
                        $data['sub_idt'] = $sub_name->id;
                        $data['sub_count'] = count($subjagg) > 1 ? 1 : 0;
                        $arrdata['data'][] = $data;
                    endif;
                    $compare = $min_val;
                }
            }
            if (count($arrdata) > 0) {
                $maindata[] = $arrdata;
            }
            $k = 200;
        }

        $manex = Mainexam ::findFirst('id=' . $params['exam_id']);
        $this->view->exam_name = $manex->exam_name;
        $this->view->compare = $compare;
    }

    public function find_childtreevalinputforreport($rowexms, $studet) {
        if (count($rowexms) > 0) {
            foreach ($rowexms as $levl) {
                foreach ($levl as $chl) {
                    $percent = 0;
                    $marks = MainexamMarks::findFirst(array(
                                'columns' => '(marks+inherited_marks) as mark ,(outof+inherited_outof) as outof ',
                                "student_id = $studet[0] and mainexam_id= $studet[1] and subject_id= $chl and grp_subject_teacher_id IN($studet[2])"
                    ));
                    $percent = $marks->mark / $marks->outof * 100;
                    if ($percent >= 40 || $percent == 0) {
                        $html .= '<td class="viewmark">'
                                . '<span class="label label-info">' . (isset($marks->mark) ? ($marks->mark . '/' . $marks->outof ) : '') . '</span>'
                                . '</td>';
                    } if ($percent > 0 && $percent < 40) {
                        $html .= '<td class="viewmark colorval" id="" >'
                                . '<span class="label label-danger">' . (isset($marks->mark) ? ($marks->mark . '/' . $marks->outof ) : '') . '</span>'
                                . '</td>';
                    }
                }
            }
        }
        return $html;
    }

}
