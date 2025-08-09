import React, { useState, useRef } from 'react';
import { Play, Pause, Settings } from 'lucide-react';

export default function NativeVideoPlayer({ 
    url, 
    width = '100%', 
    height = '400px',
    controls = true
}) {
    const [playing, setPlaying] = useState(false);
    const [isReady, setIsReady] = useState(false);
    const [playbackRate, setPlaybackRate] = useState(1);
    const [showSpeedMenu, setShowSpeedMenu] = useState(false);
    const [volume, setVolume] = useState(1);
    const [muted, setMuted] = useState(false);
    const videoRef = useRef(null);
    
    const speedOptions = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
    
    // Simple URL processing
    let videoUrl = url;
    if (url && url.startsWith('/storage/')) {
        videoUrl = `${window.location.origin}${url}`;
        // Encode spaces in filename
        if (videoUrl.includes(' ')) {
            const parts = videoUrl.split('/');
            const filename = parts[parts.length - 1];
            const encodedFilename = encodeURIComponent(filename);
            parts[parts.length - 1] = encodedFilename;
            videoUrl = parts.join('/');
        }
    }
    
    console.log('NativeVideoPlayer - URL:', videoUrl);
    
    const handlePlayPause = () => {
        console.log('🎬 Native play/pause clicked, current playing:', playing);
        if (videoRef.current) {
            if (playing) {
                videoRef.current.pause();
                console.log('⏸️ Native video paused');
            } else {
                videoRef.current.play()
                    .then(() => {
                        console.log('▶️ Native video started playing');
                    })
                    .catch(error => {
                        console.error('❌ Native video play failed:', error);
                    });
            }
            setPlaying(!playing);
        }
    };
    
    const handleSpeedChange = (speed) => {
        setPlaybackRate(speed);
        setShowSpeedMenu(false);
        if (videoRef.current) {
            videoRef.current.playbackRate = speed;
            console.log('🏃 Speed changed to:', speed);
        }
    };
    
    const handleVolumeChange = (value) => {
        const newVolume = parseFloat(value);
        setVolume(newVolume);
        setMuted(newVolume === 0);
        if (videoRef.current) {
            videoRef.current.volume = newVolume;
            videoRef.current.muted = newVolume === 0;
        }
    };
    
    const handleMute = () => {
        const newMuted = !muted;
        setMuted(newMuted);
        if (videoRef.current) {
            videoRef.current.muted = newMuted;
        }
    };
    
    const handleLoadedMetadata = () => {
        console.log('✅ Native video metadata loaded');
        setIsReady(true);
        // Set initial playback rate and volume
        if (videoRef.current) {
            videoRef.current.playbackRate = playbackRate;
            videoRef.current.volume = volume;
        }
    };
    
    const handleError = (e) => {
        console.error('❌ Native video error:', e.target.error);
    };
    
    return (
        <div 
            className="relative bg-black rounded-lg overflow-hidden"
            style={{ width, height }}
        >
            <video
                ref={videoRef}
                src={videoUrl}
                width="100%"
                height="100%"
                onLoadedMetadata={handleLoadedMetadata}
                onError={handleError}
                onPlay={() => {
                    console.log('▶️ Native video play event');
                    setPlaying(true);
                }}
                onPause={() => {
                    console.log('⏸️ Native video pause event');
                    setPlaying(false);
                }}
                preload="metadata"
                playsInline
            />
            
            {controls && (
                <div className="absolute inset-0">
                    {/* Center Play/Pause Button */}
                    <div className="absolute inset-0 flex items-center justify-center">
                        <button
                            onClick={handlePlayPause}
                            disabled={!isReady}
                            className="bg-black/50 hover:bg-black/70 text-white rounded-full p-4 transition-all duration-200 disabled:opacity-50"
                        >
                            {playing ? 
                                <Pause className="h-8 w-8" /> : 
                                <Play className="h-8 w-8 ml-1" />
                            }
                        </button>
                    </div>
                    
                    {/* Bottom Controls */}
                    <div className="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/60 to-transparent">
                        <div className="flex items-center justify-between">
                            {/* Volume Control */}
                            <div className="flex items-center space-x-2">
                                <button
                                    onClick={handleMute}
                                    className="text-white hover:text-gray-300 p-1"
                                >
                                    {muted ? '🔇' : '🔊'}
                                </button>
                                <input
                                    type="range"
                                    min={0}
                                    max={1}
                                    step={0.1}
                                    value={muted ? 0 : volume}
                                    onChange={(e) => handleVolumeChange(e.target.value)}
                                    className="w-16 h-1 bg-white/30 rounded-lg appearance-none cursor-pointer"
                                />
                            </div>
                            
                            {/* Speed Control */}
                            <div className="relative">
                                <button
                                    onClick={() => setShowSpeedMenu(!showSpeedMenu)}
                                    className="text-white hover:text-gray-300 p-1 flex items-center space-x-1"
                                >
                                    <Settings className="h-4 w-4" />
                                    <span className="text-xs">{playbackRate}x</span>
                                </button>
                                
                                {showSpeedMenu && (
                                    <div className="absolute bottom-full right-0 mb-2 bg-black/90 backdrop-blur-sm rounded-lg p-2 space-y-1 border border-white/20 shadow-lg">
                                        <div className="text-white text-xs font-medium px-2 py-1 border-b border-white/20 mb-1">
                                            Playback Speed
                                        </div>
                                        {speedOptions.map((speed) => (
                                            <button
                                                key={speed}
                                                onClick={() => handleSpeedChange(speed)}
                                                className={`block w-full text-left px-3 py-1 text-xs rounded hover:bg-white/20 transition-colors ${
                                                    playbackRate === speed ? 'bg-white/30 text-white' : 'text-gray-300'
                                                }`}
                                            >
                                                {speed === 1 ? 'Normal' : `${speed}x`}
                                                {speed === 2 && ' 🏃‍♂️'}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}
            
            {/* Debug info */}
            <div className="absolute top-2 left-2 text-white text-xs bg-black/70 px-2 py-1 rounded">
                Native Video - Ready: {isReady ? 'Yes' : 'No'} | Playing: {playing ? 'Yes' : 'No'} | Speed: {playbackRate}x
            </div>
        </div>
    );
}
