function showToast(header, message, color = "primary") {
  const toast = document.createElement("ion-toast");
  toast.header = header;
  toast.message = message;
  toast.color = color;
  toast.duration = 2000;
  toast.position = "top";
  document.body.appendChild(toast);
  toast.present();
}
