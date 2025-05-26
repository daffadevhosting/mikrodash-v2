  function showToast(message, color = 'primary', duration = 3000) {
    const toast = document.getElementById('global-toast');
    toast.message = message;
    toast.color = color;
    toast.duration = duration;
    toast.present();
  }