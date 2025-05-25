// Traffic chart dengan efek glow dan data dummy realtime
const ctx = document.getElementById('trafficChart').getContext('2d');

const gradient = ctx.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, '#00c6ff');
gradient.addColorStop(1, '#0072ff');

const trafficData = {
  labels: Array.from({ length: 20 }, (_, i) => `-${20 - i}s`),
  datasets: [{
    label: 'Traffic Mbps',
    data: Array.from({ length: 20 }, () => Math.random() * 10 + 2),
    fill: true,
    backgroundColor: gradient,
    borderColor: '#00c6ff',
    borderWidth: 2,
    tension: 0.3,
    pointRadius: 0
  }]
};

const trafficChart = new Chart(ctx, {
  type: 'line',
  data: trafficData,
  options: {
    responsive: true,
    plugins: {
      legend: {
        display: false
      }
    },
    scales: {
      x: {
        ticks: { color: '#ccc' },
        grid: { color: 'rgba(255,255,255,0.05)' }
      },
      y: {
        beginAtZero: true,
        ticks: { color: '#ccc' },
        grid: { color: 'rgba(255,255,255,0.05)' }
      }
    }
  }
});

// Update realtime data setiap detik
setInterval(() => {
  firebase.auth().currentUser.getIdToken().then(token => {
    fetch("{{site.localurl}}/php/traffic_realtime.php", {
      headers: { Authorization: "Bearer " + token }
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const rx = data.download;
        const tx = data.upload;

        // Update chart
        trafficData.labels.push("Now");
        trafficData.labels.shift();
        trafficData.datasets[0].data.push(rx);
        trafficData.datasets[0].data.shift();

        trafficChart.update();
        updateSpeed(tx, rx); // update ring display juga
      }
    });
  });
}, 1000);
