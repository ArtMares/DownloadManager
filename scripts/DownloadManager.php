<?php

/**
 * @author              Dmitriy Dergachev (ArtMares)
 * @date                28.03.2017
 * @copyright           artmares@influ.su
 */

require_once __DIR__.'/Queue.php';

class DownloadManager extends QObject {
    
    const Stopped   = 0x00;
    const Download  = 0x01;
    
    public $signals = [
        'finished()',
        'error(string)',
        'currentProgress(int,int,double)'
    ];
    
    protected $state;

    protected $manager;
    
    protected $queue;
    
    protected $list = [];
    
    protected $time;
    
    protected $current;
    
    protected $count = 0;
    
    protected $totalCount = 0;
    
    protected $output;
    
    protected $path = 'downloads/';
    
    public function __construct($parent = null) {
        parent::__construct($parent);
        $this->manager = new QNetworkAccessManager();
        $this->queue = new Queue();
        $this->time = new QTime();
        $this->output = new QFile();
        $this->path = \QStandardPaths::writableLocation(\QStandardPaths::HomeLocation).'/DownloadManager/';
        $dir = new QDir($this->path);
        if(!$dir->exists()) $dir->mkpath($this->path);
        $this->state = self::Stopped;
    }
    
    public function setDownloadPath($path) {
        $this->path = $path;
    }
    
    public function append($url) {
        if(is_array($url)) {
            foreach($url as $_url) $this->append($_url);
            if($this->queue->isEmpty()) {
                $this->emit('finished()', []);
            }
        }
        if(is_string($url)) {
            $this->_append(new QUrl($url));
        }
        if(is_object($url) && get_class($url) === 'QUrl') {
            $this->_append($url);
        }
    }
    
    protected function _append($url) {
        $this->queue->put($url);
        $this->totalCount++;
        $this->startNextDownload();
    }
    
    public function saveFileName(&$url) {
        $path = $url->path();
        $basename = (new QFileInfo($path))->fileName();
        if($basename === '') {
            $basename = 'download';
        }
        if(QFile::exists($basename)) {
            $i = 0;
            $basename .= '.';
            while(QFile::exists($basename . $i)) {
                $i++;
            }
            $basename .= $i;
        }
        return $basename;
    }
    
    protected function tmpFileName(&$url) {
        $path = $url->path();
        $fi = new QFileInfo($path);
        $basename = $fi->fileName();
        if($basename === '') {
            $basename = 'tmp';
        } else {
            $basename = str_replace('.'.$fi->suffix(), '', $basename);
        }
        return $this->path.$basename.'.download';
    }
    
    public function startNextDownload() {
        if($this->state === self::Stopped) {
            if ($this->queue->isEmpty()) {
                $this->emit('finished()', []);
                return;
            }
            $url = $this->queue->get();
            $filename = $this->tmpFileName($url);
            $this->output->setFileName($filename);
            if (!$this->output->open(QIODevice::WriteOnly)) {
                $this->emit('error(string)', ['Problem opening save file ' . $filename . ' : ' . $this->output->errorString()]);
                $this->startNextDownload();
                return;
            }
            $request = new QNetworkRequest($url);
            $request->setSslConfiguration(QSslConfiguration::defaultConfiguration());
            $this->state = self::Download;
            $this->current = $this->manager->get($request);
            $this->current->connect(SIGNAL('downloadProgress(int,int)'), $this, SLOT('downloadProgress(int,int)'));
            $this->current->connect(SIGNAL('finished()'), $this, SLOT('downloadFinished()'));
            $this->current->connect(SIGNAL('readyRead()'), $this, SLOT('downloadReadyRead()'));
            $this->time->start();
        }
    }
    
    public function downloadProgress($sender, $bytesReceived, $bytesTotal) {
        $speed = $bytesReceived * 1000 / $this->time->elapsed();
        $this->emit('currentProgress(int,int,double)', [$bytesReceived, $bytesTotal, $speed]);
        unset($bytesReceived, $bytesTotal, $speed);
    }
    
    public function downloadFinished() {
        $this->output->close();
        $error = $this->current->error();
        if($error) {
            $this->emit('error(string)', ['Failed: '. $this->current->errorString()]);
        } else {
            if($error == QNetworkReply::NoError) {
                $statusCode = $this->current->attribute(QNetworkRequest::HttpStatusCodeAttribute)->toInt();
                if($statusCode === 301 || $statusCode === 302) {
                    $redirect = $this->current->attribute(QNetworkRequest::RedirectionTargetAttribute)->toUrl();
                    if(!$redirect->isEmpty()) {
                        $this->append($redirect);
                    }
                }
            }
            $this->count++;
        }
        $this->current->deleteLater();
        $this->state = self::Stopped;
        $this->manager->clearAccessCache();
        $this->startNextDownload();
    }
    
    public function downloadReadyRead() {
        $this->output->write($this->current->readAll());
        $this->output->flush();
    }
}