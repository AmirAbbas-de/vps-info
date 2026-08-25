// select form

// Elements
const searchInput = document.getElementById('search');
const serverGroupSelect = document.getElementById('server-group');
const subnetSelect = document.getElementById('subnet');
const serverNameSelect = document.getElementById('server-name');
const osFilterSelect = document.getElementById('os-filter');
const ramFilterSelect = document.getElementById('ram-filter');
const statusFilterSelect = document.getElementById('status-filter');
const btnExportFile = document.getElementById('btn-export-file');
const vpsTableBody = document.getElementById('vps-tbody');
const daySelection = document.getElementById('day-select');
const timeSelecion = document.getElementById('time-select');
const windowsCount =
  document.getElementById('windows-count') || document.querySelector('.widget-item.windows .sub-text');
const linuxCount = document.getElementById('linux-count') || document.querySelector('.widget-item.linux .sub-text');
const mikrotikCount =
  document.getElementById('mikrotik-count') || document.querySelector('.widget-item.mikrotik .sub-text');
const totalVpsCount = document.getElementById('total-vps') || document.querySelector('.widget-item.total .sub-text');

// Sync a native select's options/state with its TomSelect instance (if present)
function syncTomSelect(selectElem) {
  if (!selectElem) return;
  try {
    if (window.TS && window.TS[selectElem.id]) {
      const inst = window.TS[selectElem.id];
      inst.clearOptions();
      Array.from(selectElem.options).forEach(opt => {
        inst.addOption({ value: opt.value, text: opt.textContent || opt.text });
      });
      inst.refreshOptions(false);
      if (selectElem.disabled) inst.disable();
      else inst.enable();
    }
  } catch (e) {
    console.warn('syncTomSelect error for', selectElem && selectElem.id, e);
  }
}

// Populate subnets and server names based on server group
serverGroupSelect.addEventListener('change', function () {
  const selectedGroup = this.value;
  subnetSelect.innerHTML = '<option value="">All</option>';
  serverNameSelect.innerHTML = '<option value="">All</option>';
  if (selectedGroup && ippoolsData[selectedGroup]) {
    ippoolsData[selectedGroup]
      .sort((a, b) => a.subnet.localeCompare(b.subnet))
      .forEach(pool => {
        let count = 0;
        Object.values(vpsData).forEach(vps => {
          const vpsIP = vps.ips.split(',')[0];
          if (isIPInSubnet(vpsIP, pool.subnet)) {
            count++;
          }
        });
        const option = document.createElement('option');
        option.value = pool.subnet;
        option.textContent = pool.subnet + ' (' + pool.name + ') (' + count + ')';
        subnetSelect.appendChild(option);
      });
    subnetSelect.disabled = false;
    document.getElementById('subnet-label').textContent = `Subnet (${ippoolsData[selectedGroup].length})`;
    // Populate server names for the group
    const serverNameCounts = {};
    Object.values(vpsData).forEach(vps => {
      const vpsIP = vps.ips.split(',')[0];
      const groupSubnets = ippoolsData[selectedGroup] || [];
      if (groupSubnets.some(pool => isIPInSubnet(vpsIP, pool.subnet))) {
        serverNameCounts[vps.server_name] = (serverNameCounts[vps.server_name] || 0) + 1;
      }
    });
    Object.keys(serverNameCounts)
      .sort()
      .forEach(name => {
        // Count statuses for this server name
        let statusCounts = { Online: 0, Offline: 0, Suspend: 0, Unknown: 0 };
        Object.values(vpsData).forEach(vps => {
          const vpsIP = vps.ips.split(',')[0];
          const groupSubnets = ippoolsData[selectedGroup] || [];
          if (vps.server_name === name && groupSubnets.some(pool => isIPInSubnet(vpsIP, pool.subnet))) {
            if (statusCounts.hasOwnProperty(vps.status)) {
              statusCounts[vps.status]++;
            } else {
              statusCounts.Unknown++;
            }
          }
        });

        const option = document.createElement('option');
        option.value = name;
        option.textContent = `${name} (${serverNameCounts[name]}) - Online: ${statusCounts.Online} | Offline: ${statusCounts.Offline} | Suspend: ${statusCounts.Suspend} | Unknown: ${statusCounts.Unknown}`;

        serverNameSelect.appendChild(option);
      });
    serverNameSelect.disabled = false;
    document.getElementById('server-name-label').textContent = `Server Name (${Object.keys(serverNameCounts).length})`;
  } else if (selectedGroup === 'redstation') {
    const redstationGroups = Object.keys(ippoolsData).filter(g => g.toLowerCase().includes('redstation'));
    const redstationPools = [];
    redstationGroups.forEach(g => {
      if (ippoolsData[g]) redstationPools.push(...ippoolsData[g]);
    });
    redstationPools
      .sort((a, b) => a.subnet.localeCompare(b.subnet))
      .forEach(pool => {
        let count = 0;
        Object.values(vpsData).forEach(vps => {
          const vpsIP = vps.ips.split(',')[0];
          if (isIPInSubnet(vpsIP, pool.subnet)) {
            count++;
          }
        });
        const option = document.createElement('option');
        option.value = pool.subnet;
        option.textContent = pool.subnet + ' (' + pool.name + ') (' + count + ')';
        subnetSelect.appendChild(option);
      });
    subnetSelect.disabled = false;
    document.getElementById('subnet-label').textContent = `Subnet (${redstationPools.length})`;
    // Populate server names for redstation
    const serverNameCounts = {};
    Object.values(vpsData).forEach(vps => {
      const vpsIP = vps.ips.split(',')[0];
      if (redstationPools.some(pool => isIPInSubnet(vpsIP, pool.subnet))) {
        serverNameCounts[vps.server_name] = (serverNameCounts[vps.server_name] || 0) + 1;
      }
    });
    Object.keys(serverNameCounts)
      .sort()
      .forEach(name => {
        // Count statuses for this server name
        let statusCounts = { Online: 0, Offline: 0, Suspend: 0, Unknown: 0 };
        Object.values(vpsData).forEach(vps => {
          const vpsIP = vps.ips.split(',')[0];
          if (vps.server_name === name && redstationPools.some(pool => isIPInSubnet(vpsIP, pool.subnet))) {
            if (statusCounts.hasOwnProperty(vps.status)) {
              statusCounts[vps.status]++;
            } else {
              statusCounts.Unknown++;
            }
          }
        });

        const option = document.createElement('option');
        option.value = name;
        option.textContent = `${name} (${serverNameCounts[name]}) - Online: ${statusCounts.Online} | Offline: ${statusCounts.Offline} | Suspend: ${statusCounts.Suspend} | Unknown: ${statusCounts.Unknown}`;

        serverNameSelect.appendChild(option);
      });
    serverNameSelect.disabled = false;
    document.getElementById('server-name-label').textContent = `Server Name (${Object.keys(serverNameCounts).length})`;
  } else if (selectedGroup && ippoolsData[selectedGroup]) {
    ippoolsData[selectedGroup]
      .sort((a, b) => a.subnet.localeCompare(b.subnet))
      .forEach(pool => {
        let count = 0;
        Object.values(vpsData).forEach(vps => {
          const vpsIP = vps.ips.split(',')[0];
          if (isIPInSubnet(vpsIP, pool.subnet)) {
            count++;
          }
        });
        const option = document.createElement('option');
        option.value = pool.subnet;
        option.textContent = pool.subnet + ' (' + pool.name + ') (' + count + ')';
        subnetSelect.appendChild(option);
      });
    subnetSelect.disabled = false;
    document.getElementById('subnet-label').textContent = `Subnet (${ippoolsData[selectedGroup].length})`;
    // Populate server names for the group
    const serverNameCounts = {};
    Object.values(vpsData).forEach(vps => {
      const vpsIP = vps.ips.split(',')[0];
      const groupSubnets = ippoolsData[selectedGroup] || [];
      if (groupSubnets.some(pool => isIPInSubnet(vpsIP, pool.subnet))) {
        serverNameCounts[vps.server_name] = (serverNameCounts[vps.server_name] || 0) + 1;
      }
    });
    Object.keys(serverNameCounts)
      .sort()
      .forEach(name => {
        // Count statuses for this server name
        let statusCounts = { Online: 0, Offline: 0, Suspend: 0, Unknown: 0 };
        Object.values(vpsData).forEach(vps => {
          const vpsIP = vps.ips.split(',')[0];
          const groupSubnets = ippoolsData[selectedGroup] || [];
          if (vps.server_name === name && groupSubnets.some(pool => isIPInSubnet(vpsIP, pool.subnet))) {
            statusCounts[vps.status]++;
          }
        });

        const option = document.createElement('option');
        option.value = name;
        option.textContent = `${name} (${serverNameCounts[name]}) - Online: ${statusCounts.Online} | Offline: ${statusCounts.Offline} | Suspend: ${statusCounts.Suspend} | Unknown: ${statusCounts.Unknown}`;

        serverNameSelect.appendChild(option);
      });
    serverNameSelect.disabled = false;
    document.getElementById('server-name-label').textContent = `Server Name (${Object.keys(serverNameCounts).length})`;
  } 
    else {
    subnetSelect.disabled = false;
    serverNameSelect.disabled = false;
    document.getElementById('subnet-label').textContent = 'Subnet';
    document.getElementById('server-name-label').textContent = 'Server Name';
    // Populate all subnets
    const allSubnets = [];
    Object.values(ippoolsData).forEach(groupPools => {
      groupPools.forEach(pool => {
        allSubnets.push(pool);
      });
    });
    allSubnets.sort((a, b) => a.subnet.localeCompare(b.subnet));
    subnetSelect.innerHTML = '<option value="">All</option>';
    allSubnets.forEach(pool => {
      const option = document.createElement('option');
      option.value = pool.subnet;
      option.textContent = pool.subnet + ' (' + pool.name + ')';
      subnetSelect.appendChild(option);
    });
    document.getElementById('subnet-label').textContent = `Subnet (${allSubnets.length})`;
    // Populate all server names
    const allServerNames = [...new Set(Object.values(vpsData).map(vps => vps.server_name))].sort();
    serverNameSelect.innerHTML = '<option value="">All</option>';
    allServerNames.forEach(name => {
      const option = document.createElement('option');
      option.value = name;
      option.textContent = name;
      serverNameSelect.appendChild(option);
    });
    document.getElementById('server-name-label').textContent = `Server Name (${allServerNames.length})`;
  }
  // Sync TomSelect UI after repopulating native selects
  syncTomSelect(subnetSelect);
  syncTomSelect(serverNameSelect);
  filterTable();
});

// Populate server names based on subnet
subnetSelect.addEventListener('change', function () {
  const selectedSubnet = this.value;
  serverNameSelect.innerHTML = '<option value="">All</option>';
  if (selectedSubnet) {
    const serverNameCounts = {};
    Object.values(vpsData).forEach(vps => {
      const vpsIP = vps.ips.split(',')[0];
      if (isIPInSubnet(vpsIP, selectedSubnet)) {
        serverNameCounts[vps.server_name] = (serverNameCounts[vps.server_name] || 0) + 1;
      }
    });
    Object.keys(serverNameCounts)
      .sort()
      .forEach(name => {
        const option = document.createElement('option');
        option.value = name;
        option.textContent = `${name} (${serverNameCounts[name]})`;
        serverNameSelect.appendChild(option);
      });
    serverNameSelect.disabled = false;
    document.getElementById('server-name-label').textContent = `Server Name (${Object.keys(serverNameCounts).length})`;
  } else {
    serverNameSelect.disabled = true;
    document.getElementById('server-name-label').textContent = 'Server Name';
  }
  // Sync TomSelect UI for server-name after repopulating
  syncTomSelect(serverNameSelect);
  filterTable();
});
var filteredVPSMain = ''
// Filter table
function filterTable() {
  const searchTerm = searchInput.value.toLowerCase();
  const selectedGroup = serverGroupSelect.value;
  const selectedSubnet = subnetSelect.value;
  const selectedServerName = serverNameSelect.value;
  const selectedOS = osFilterSelect.value;
  const selectedRAM = ramFilterSelect.value;
  const selectedStatus = statusFilterSelect.value;

  let filteredVPS = Object.values(vpsData);

  filteredVPS = filteredVPS.filter(vps => {
    // Search
    if (searchTerm && !Object.values(vps).some(val => String(val).toLowerCase().includes(searchTerm))) {
      return false;
    }
    // Server Group
    if (selectedGroup) {
      const vpsIP = vps.ips.split(',')[0];
      let groupSubnets = [];
      if (selectedGroup === 'redstation') {
        const redstationGroups = Object.keys(ippoolsData).filter(g => g.toLowerCase().includes('Redstation'));
        redstationGroups.forEach(g => {
          if (ippoolsData[g]) groupSubnets.push(...ippoolsData[g]);
        });
      } else {
        groupSubnets = ippoolsData[selectedGroup] || [];
      }
      const inGroup = groupSubnets.some(pool => isIPInSubnet(vpsIP, pool.subnet));
      if (!inGroup) return false;
    }
    // Subnet - check if vps IP is in subnet
    if (selectedSubnet) {
      const vpsIP = vps.ips.split(',')[0];
      if (!isIPInSubnet(vpsIP, selectedSubnet)) {
        return false;
      }
    }
    // Server Name
    if (selectedServerName && vps.server_name !== selectedServerName) {
      return false;
    }
    return true;
  });

  // Count OS, RAM, Status from filtered by location
  let windows = 0,
    linux = 0,
    mikrotik = 0;
  let ram2gb = 0,
    ram4gb = 0,
    ram8gb = 0,
    ram16gb = 0;
  let statusOnline = 0,
    statusOffline = 0,
    statusSuspend = 0;

  filteredVPS.forEach(vps => {
    const os = getOSGroup(vps.os_name);
    if (os === 'Windows') windows++;
    else if (os === 'Linux') linux++;
    else if (os === 'Mikrotik') mikrotik++;

    const ram = parseInt(vps.ram);
    if (ram >= 2048 && ram < 4096) ram2gb++;
    else if (ram >= 4096 && ram < 8192) ram4gb++;
    else if (ram >= 8192 && ram < 16384) ram8gb++;
    else if (ram >= 16384) ram16gb++;

    if (vps.status === 'Online') statusOnline++;
    else if (vps.status === 'Offline') statusOffline++;
    else if (vps.status === 'Suspend') statusSuspend++;
  });

  // Now apply OS, RAM, Status filters
  filteredVPS = filteredVPS.filter(vps => {
    // OS
    const os = getOSGroup(vps.os_name);
    if (selectedOS && os !== selectedOS) {
      return false;
    }
    // RAM
    if (selectedRAM && parseInt(vps.ram) < parseInt(selectedRAM)) {
      return false;
    }
    // Status
    if (selectedStatus && vps.status !== selectedStatus) {
      return false;
    }
    return true;
  });

  // Count final OS for top cards
  let finalWindows = 0,
    finalLinux = 0,
    finalMikrotik = 0;
  filteredVPS.forEach(vps => {
    const os = getOSGroup(vps.os_name);
    if (os === 'Windows') finalWindows++;
    else if (os === 'Linux') finalLinux++;
    else if (os === 'Mikrotik') finalMikrotik++;
  });

  // Update top counts (only when elements exist)
  if (windowsCount) windowsCount.textContent = finalWindows;
  if (linuxCount) linuxCount.textContent = finalLinux;
  if (mikrotikCount) mikrotikCount.textContent = finalMikrotik;
  if (totalVpsCount) totalVpsCount.textContent = filteredVPS.length;
  filteredVPSMain = filteredVPS
  // Render table
  renderTable(filteredVPS);
}

function getOSGroup(osName) {
  const os = (osName || '').toLowerCase();
  if (os.includes('windows')) return 'Windows';
  if (os.includes('mikrotik')) return 'Mikrotik';
  return 'Linux';
}

function isIPInSubnet(ip, subnet) {
  // Simple check, assuming CIDR
  const [subnetIP, mask] = subnet.split('/');
  const maskInt = parseInt(mask);
  const ipParts = ip.split('.').map(Number);
  const subnetParts = subnetIP.split('.').map(Number);
  const maskBytes = Math.floor(maskInt / 8);
  for (let i = 0; i < maskBytes; i++) {
    if (ipParts[i] !== subnetParts[i]) return false;
  }
  if (maskInt % 8 !== 0) {
    const bitMask = 255 << (8 - (maskInt % 8));
    if ((ipParts[maskBytes] & bitMask) !== (subnetParts[maskBytes] & bitMask)) return false;
  }
  return true;
}

function renderTable(vpsList) {
  vpsTableBody.innerHTML = '';
  vpsList.forEach(vps => {
    const row = document.createElement('tr');
    const os = getOSGroup(vps.os_name);

    let osIconHtml = '';
    if (os === 'Mikrotik') {
      osIconHtml = `<img src="winbox.svg" alt="Winbox" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 4px;">`;
    } else {
      const iconClass = 'fab fa-' + os.toLowerCase();
      osIconHtml = `<i class="${iconClass}"></i>`;
    }

    row.innerHTML = `
                <td>
                    <span class="status-${vps.status.toLowerCase()} status-click">
                        ${vps.status}
                    </span>
                </td>
                <td>${vps.hostname}</td>
                <td><span class="metric-value">${vps.vpsid}</span></td>
                <td>${vps.ips.split(',')[0]}</td>
                <td>${osIconHtml}</td>
                <td><small>${vps.server_name}</small></td>
                <td>${vps.location || 'N/A'}</td>
                <td>${vps.creation_date_shamsi || 'N/A'}</td>
                <td><span class="metric-value">${vps.cpu_cores}</span></td>
                <td><span class="metric-value">${(vps.ram / 1024).toFixed(1)}</span></td>
                <td><span class="metric-value">${vps.space}</span></td>
                <td><span class="metric-value">${vps.used_cpu}%</span></td>
                <td><span class="metric-value">${vps.used_ram}%</span></td>
            `;
    
    // فقط span مربوط به status رو انتخاب کن
    const statusElement = row.querySelector('.status-click');

    statusElement.addEventListener('click', e => {
      e.stopPropagation(); // جلوگیری از کلیک روی کل ردیف
      showVPSDetails(vps);
    });

    vpsTableBody.appendChild(row);
  });
}

function showVPSDetails(vps) {
  console.log('Showing details for VPS ID:', vps);
  const shownFields = [
    'status',
    'hostname',
    'vpsid',
    'ips',
    'os_name',
    'IP Location',
    'cpu_cores',
    'used_cpu',
    'ram',
    'used_ram',
    'io_read',
    'io_write',
    'used_inode',
    'space'
  ];
  const details = Object.entries(vps)
    .filter(([key]) => !shownFields.includes(key))
    .map(
      ([key, value]) => `
        <div class="row mb-2">
            <div class="col-sm-4"><strong>${key
          .replace(/_/g, ' ')
          .replace(/\b\w/g, l => l.toUpperCase())}:</strong></div>
            <div class="col-sm-8">${value}</div>
        </div>
    `
    )
    .join('');
  document.getElementById('vps-details').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Basic Information</h5>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Status:</strong></div>
                    <div class="col-sm-8"><span class="status-${vps.status.toLowerCase()}">${vps.status}</span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Hostname:</strong></div>
                    <div class="col-sm-8">${vps.hostname}</div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>VPS ID:</strong></div>
                    <div class="col-sm-8"><span class="metric-value">${vps.vpsid}</span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>IP Address:</strong></div>
                    <div class="col-sm-8">${vps.ips}</div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>OS Name:</strong></div>
                    <div class="col-sm-8">${vps.os_name}</div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>IP Location:</strong></div>
                    <div class="col-sm-8">${vps.location || 'N/A'}</div>
                </div>
            </div>
            <div class="col-md-6">
                <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Performance Metrics</h5>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>CPU Cores:</strong></div>
                    <div class="col-sm-8"><span class="metric-value">${vps.cpu_cores}</span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Used CPU:</strong></div>
                    <div class="col-sm-8"><span class="metric-value">${vps.used_cpu}%</span></div>
                </div>
                <div class="row mb-2">
                <div class="col-sm-4"><strong>RAM:</strong></div>
                <div class="col-sm-8"><span class="metric-value">${(vps.ram / 1024).toFixed(1)} GB</span></div>
                </div>
                <div class="row mb-2">
                <div class="col-sm-4"><strong>Disk:</strong></div>
                <div class="col-sm-8"><span class="metric-value">${vps.space} GB</span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Used RAM:</strong></div>
                    <div class="col-sm-8"><span class="metric-value">${vps.used_ram}%</span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>IO Read:</strong></div>
                    <div class="col-sm-8"><span class="metric-value">${vps.io_read || '0'}</span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>IO Write:</strong></div>
                    <div class="col-sm-8"><span class="metric-value">${vps.io_write || '0'}</span></div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Used Inodes:</strong></div>
                    <div class="col-sm-8"><span class="metric-value">${vps.used_inode || '0'}</span></div>
                </div>
            </div>
        </div>
        <hr>
        <h5 class="mb-3"><i class="fas fa-cogs me-2"></i>Additional Details</h5>
        ${details}
    `;
  console.log(vps);
  new bootstrap.Modal(document.getElementById('vpsModal')).show();
}

// Sorting
document.querySelectorAll('.sortable').forEach(header => {
  header.addEventListener('click', function () {
    const sortBy = this.dataset.sort;
    const sortDir = this.dataset.sortDir;
    // helper to safely read cell text
    function cellText(row, idx) {
      const c = row.querySelectorAll('td')[idx];
      return c ? c.textContent.trim() : '';
    }

    const currentVPS = Array.from(vpsTableBody.querySelectorAll('tr')).map(row => {
      return {
        status: cellText(row, 0),
        hostname: cellText(row, 1),
        vpsid: parseInt(cellText(row, 2)) || 0,
        ips: cellText(row, 3),
        os: cellText(row, 4),
        server_name: cellText(row, 5),
        location: cellText(row, 6),
        saved_at: cellText(row, 7),
        cpu_cores: parseInt(cellText(row, 8)) || 0,
        ram: parseFloat(cellText(row, 9)) || 0,
        space: parseInt(cellText(row, 10)) || 0,
        used_cpu: parseFloat(cellText(row, 11)) || 0,
        used_ram: parseInt(cellText(row, 12)) || 0,
        element: row
      };
    });
    currentVPS.sort((a, b) => {
      let cmp;
      if (typeof a[sortBy] === 'string') {
        cmp = a[sortBy].localeCompare(b[sortBy]);
      } else {
        cmp = a[sortBy] - b[sortBy];
      }
      return sortDir === 'asc' ? cmp : -cmp;
    });
    vpsTableBody.innerHTML = '';
    currentVPS.forEach(item => vpsTableBody.appendChild(item.element));
    // Toggle sort direction
    this.dataset.sortDir = sortDir === 'asc' ? 'desc' : 'asc';
  });
});

// Debounce search input
let searchTimeout;
searchInput.addEventListener('input', function () {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(filterTable, 300);
});

// Event listeners for filters
serverNameSelect.addEventListener('change', filterTable);
osFilterSelect.addEventListener('change', filterTable);
ramFilterSelect.addEventListener('change', filterTable);
statusFilterSelect.addEventListener('change', filterTable);

// Check for URL parameters
const urlParams = new URLSearchParams(window.location.search);
const searchParam = urlParams.get('search');
const subnetParam = urlParams.get('subnet');
const filterSubnetParam = urlParams.get('filter_subnet');

if (searchParam) {
  searchInput.value = searchParam;
}

const targetSubnet = subnetParam || filterSubnetParam;
if (targetSubnet) {
  // Find the group for this subnet and set it
  for (const [group, pools] of Object.entries(ippoolsData)) {
    if (pools.some(pool => pool.subnet === targetSubnet)) {
      serverGroupSelect.value = group;
      // Trigger the change event to populate subnets
      serverGroupSelect.dispatchEvent(new Event('change'));
      // Wait a bit for the options to be populated, then select the subnet
      setTimeout(() => {
        subnetSelect.value = targetSubnet;
        filterTable();
      }, 100);
      break;
    }
  }
}

function jsonToCsv(jsonData) {
  if (!jsonData.length) return '';

  const headers = Object.keys(jsonData[0]);
  const csvRows = [];

  csvRows.push(headers.join(',')); // header

  for (const row of jsonData) {
    const values = headers.map(header => {
      const val = row[header];
      if (typeof val === 'string' && (val.includes(',') || val.includes('"'))) {
        return `"${val.replace(/"/g, '""')}"`;
      }
      return val;
    });
    csvRows.push(values.join(','));
  }

  return csvRows.join('\r\n'); // استفاده از \r\n برای سازگاری بهتر با Excel
}

// دانلود CSV با نام درست
function downloadCsv(filename, jsonData) {
  const arrayData = Object.keys(jsonData).map(key => ({ vpsid: key, ...jsonData[key] }));
  const csv = jsonToCsv(arrayData);

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = filename; // حتماً نام فایل با .csv
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

btnExportFile.addEventListener('click', () => {
  downloadCsv('vpsData.csv', filteredVPSMain);
});
// Event listener for recorded-times
// مطمئن شو که به اینسنتس TomSelect دسترسی داری
const timeSelectTom = window.TS['time-select'];

if (timeSelectTom) {
    timeSelectTom.on('change', function(value) {
        const selectedDay = document.getElementById('day-select').value;
        const selectedTime = timeSelecion.value; 
        if (selectedDay && selectedTime) {
            const params = new URLSearchParams(window.location.search);
            params.set('saved_at', `${selectedDay} ${selectedTime}`);
            window.location.search = params.toString();
        }
    });
}
// Initial render: ensure TomSelects reflect native select state/options
syncTomSelect(serverGroupSelect);
syncTomSelect(subnetSelect);
syncTomSelect(serverNameSelect);
// Initial population
filterTable();
