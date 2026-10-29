nc_audiocaptcha = function(playlist) {
    this.playlist = JSON.parse(playlist)?.playlist;
    this.audio = new Audio();
    this.currentTrackIndex = 0;
    this.currentTrack = null;
};

nc_audiocaptcha.prototype = {
    play: function() {
        let player = this;
        this.currentTrackIndex = 0;
        this.currentTrack = this.playlist[this.currentTrackIndex];
        this.audio.src = this.currentTrack.file;
        this.audio.onended = function () {
            player.nextTrack();
        };
        this.audio.play();
    },
    nextTrack: function() {
        if (this.currentTrackIndex === this.playlist.length - 1) {
            this.audio.pause();
            this.audio.currentTime = 0;
        } else {
            this.currentTrackIndex++;
            this.currentTrack = this.playlist[this.currentTrackIndex];
            this.audio.src = this.currentTrack.file;
            this.audio.play();
        }
    }
};