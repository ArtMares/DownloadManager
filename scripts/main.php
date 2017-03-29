<?php

# QNetworkReply

$app = new QApplication($argc, $argv);

require_once __DIR__.'/DownloadManager.php';

class MainWindow extends QWidget {
    
    protected $dm;
    
    protected $line;
    
    protected $progress;
    
    protected $p;
    
    protected $m;
    
    public function __construct($parent = null) {
        parent::__construct($parent);
        
        $this->dm = new DownloadManager();
        
        $this->initComponents();
    }
    
    private function initComponents() {
        $this->line = new QLineEdit($this);
        
        $add = new QPushButton($this);
        $add->text = 'Add';
        $add->onClicked = function() {
            $url = $this->line->text();
            if($url !== '') {
                $this->dm->append($url);
            }
        };
        
        $mass = new QPushButton($this);
        $mass->text = 'Mass';
        $mass->onClicked = function() {
            $urls = [
                'http://webfonts.ru/original/firasanscompressed/firasanscompressed.zip',
                'http://webfonts.ru/original/firasans/firasans.zip',
                'http://webfonts.ru/original/akrobat/akrobat.zip'
            ];
            $this->dm->append($urls);
        };
        
        $free = new QPushButton($this);
        $free->text = 'Delete';
        $free->onClicked = function() {
            qDebug('Delete');
            $this->dm = null;
        };
        
        $this->p = new QProgressBar($this);
        
        $this->progress = new QLabel($this);
        
        $this->m = new QLabel($this);
        
        $this->setLayout(new QGridLayout());
        $row = 0;
        $this->layout()->addWidget($this->line, $row, 0);
        $this->layout()->addWidget($add, $row, 1);
        $this->layout()->addWidget($mass, $row, 2);
        $row++;
        $this->layout()->addWidget($this->m, $row, 0, 1, 3);
        $row++;
        $this->layout()->addWidget($this->progress, $row, 0, 1, 3);
        $row++;
        $this->layout()->addWidget($this->p, $row, 0, 1, 3);
        
        $this->resize(new QSize(500, 250));
        
        $this->dm->connect(SIGNAL('currentProgress(int,int,double)'), $this, SLOT('updateProgress(int,int,double)'));
        $this->dm->connect(SIGNAL('error(string)'), $this, SLOT('error(string)'));
        $this->dm->connect(SIGNAL('finished()'), $this, SLOT('finished()'));
    }
    
    public function updateProgress($sender, $received, $total, $speed) {
        $unit = '';
        if ($speed < 1024) {
            $unit = "B/s";
        } else if ($speed < 1024*1024) {
            $speed /= 1024;
            $unit = "kB/s";
        } else {
            $speed /= 1024*1024;
            $unit = "mB/s";
        }
        $this->progress->text = $received.' / '.$total . ' : '.$speed.' '.$unit;
        $this->p->setMaximum($total);
        $this->p->setValue($received);
        $this->m->text = memory_get_usage();
        unset($sender, $received, $total, $speed, $unit);
    }
    
    public function finished() {
        $this->progress->text = 'Finished';
        $this->m->text = memory_get_usage();
    }
    
    public function error($sender, $msg) {
        $this->progress->text = $msg;
        $this->m->text = memory_get_usage();
    }
}

$window = new MainWindow;
$window->show();

return $app->exec();
