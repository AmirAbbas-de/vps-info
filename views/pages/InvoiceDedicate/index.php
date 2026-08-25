<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Management Table</title>
    <link rel="stylesheet" href="../../../src/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../src/fonts/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            padding: 20px;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead th {
            text-align: left;
            padding: 15px;
            color: #888;
            font-weight: 500;
            border-bottom: 1px solid #eee;
        }

        tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px dashed #eee;
            /* خط‌چین بین ردیف‌ها */
        }

        /* استایل بخش نام و مشخصات */
        .name-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .server-name {
            color: #6a4caf;
            font-weight: bold;
            display: block;
        }

        .server-specs {
            color: #999;
            font-size: 11px;
            margin: 2px 0 0 0;
        }

        /* وضعیت Active */
        .status.active {
            color: #27ae60;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }

        /* مکان و پرچم */
        .location {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .location img {
            border-radius: 2px;
        }

        /* قیمت و تاریخ پرداخت */
        .price {
            font-weight: 600;
        }

        .paid-till {
            color: #aaa;
            font-size: 11px;
            margin: 2px 0 0 0;
        }

        /* آیکون سه نقطه */
        .action-icon {
            color: #ccc;
            cursor: pointer;
        }

        .action-icon:hover {
            color: #666;
        }

        .icon {
            border-radius: 10px;
        }

        tr.warning-week {
            background-color: #fff9c4 !important;
        }

        tr.warning-soon {
            background-color: #ffe0b2 !important;
        }

        tr.warning-urgent {
            background-color: #ffcdd2 !important;
        }

        tr[class^="warning-"] td {
            border-bottom: 1px dashed rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- DE -->
            <div class="col-6">
                <div class="table-container ">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>IP</th>
                                <th>Total expense</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>52605</td>
                                <td>
                                    <div class="name-cell">

                                        <div>
                                            <span class="server-name">DE-TRADE01</span>
                                            <p class="server-specs">2xAMD EPYC 7451 2.3GHz (24 cores)/256GB/2x1Tb NVMe m2 SSD</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location">
                                        <div class="icon"><img src="./Ger.svg" alt="" width="30px"></div> DE
                                    </div>
                                </td>
                                <td>151.243.171.46</td>
                                <td>
                                    <span class="price">€200.00 / monthly</span>
                                    <p class="paid-till">Paid till 6/10/2026</p>
                                </td>
                                <td><i class="fas fa-ellipsis-h action-icon"></i></td>
                            </tr>
                            <tr>
                                <td>58338</td>
                                <td>
                                    <div class="name-cell">

                                        <div>
                                            <span class="server-name">DE-TRADE02</span>
                                            <p class="server-specs">2xXeon Gold 6140 2.3GHz (18 cores)/256GB/2x1.92Tb SSD/2x10G</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location">
                                        <div class="icon"><img src="./Ger.svg" alt="" width="30px"></div> DE
                                    </div>
                                </td>
                                <td>91.239.211.7</td>
                                <td>
                                    <span class="price">€240.00 / monthly</span>
                                    <p class="paid-till">Paid till 6/10/2026</p>
                                </td>
                                <td><i class="fas fa-ellipsis-h action-icon"></i></td>
                            </tr>
                            <tr>
                                <td>56520</td>
                                <td>
                                    <div class="name-cell">

                                        <div>
                                            <span class="server-name">DE-TRADE06</span>
                                            <p class="server-specs">2xAMD EPYC 7451 2.3GHz (24 cores)/256GB/2x960Gb SSD</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./Ger.svg" alt="" width="30px"></div> DE
                                    </div>
                                </td>
                                <td>91.239.211.11</td>
                                <td>
                                    <span class="price">€200.00 / monthly</span>
                                    <p class="paid-till">Paid till 5/21/2026</p>
                                </td>
                                <td><i class="fas fa-ellipsis-h action-icon"></i></td>
                            </tr>
                            <tr>
                                <td>52613</td>
                                <td>
                                    <div class="name-cell">

                                        <div>
                                            <span class="server-name">DE-TRADE07</span>
                                            <p class="server-specs">2xAMD EPYC 7451 2.3GHz (24 cores)/256GB/2x960Gb SSD</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./Ger.svg" alt="" width="30px"></div> DE
                                    </div>
                                </td>
                                <td>91.239.211.12</td>
                                <td>
                                    <span class="price">€200.00 / monthly</span>
                                    <p class="paid-till">Paid till 5/28/2026</p>
                                </td>
                                <td><i class="fas fa-ellipsis-h action-icon"></i></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- US -->
                <div class="table-container mt-4">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>IP</th>
                                <th>Total expense</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>50452</td>
                                <td>
                                    <div class="name-cell">

                                        <div>
                                            <span class="server-name">USA01</span>
                                            <p class="server-specs">2xAMD EPYC 7451 2.3GHz (24 cores)/256GB/2x960Gb SSD</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./us.svg" alt="" width="30px"></div> US
                                    </div>
                                </td>
                                <td>188.209.138.6</td>
                                <td>
                                    <span class="price">€170.00 / monthly</span>
                                    <p class="paid-till">Paid till 6/4/2026</p>
                                </td>
                                <td><i class="fas fa-ellipsis-h action-icon"></i></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- NL -->
            <div class="col-6">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>IP</th>
                                <th>Total expense</th>
                            </tr>
                        </thead>
                        <tbody id="server-table-body">
                            <tr>
                                <td>16902</td>
                                <td>
                                    <div class="name-cell">
                                        <div><span class="server-name">Dezvarei01</span>
                                            <p class="server-specs">Xeon E3-1230v3 3.3GHz/32GB/960Gb SSD</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./nl.svg" alt="" width="30px"></div> NL
                                    </div>
                                </td>
                                <td>-</td>
                                <td>
                                    <div class="price">€60.00 / monthly</div>
                                    <div class="paid-till">Paid till 6/5/2026</div>
                                </td>
                            </tr>
                            <tr>
                                <td>33125</td>
                                <td>
                                    <div class="name-cell">
                                        <div><span class="server-name">NL-MGMT</span>
                                            <p class="server-specs">Xeon E5-1650 3.2GHz/64GB/2x960Gb SSD/PSU</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./nl.svg" alt="" width="30px"></div> NL
                                    </div>
                                </td>
                                <td>81.22.134.14</td>
                                <td>
                                    <div class="price">€60.00 / monthly</div>
                                    <div class="paid-till">Paid till 5/14/2026</div>
                                </td>
                            </tr>
                            <tr>
                                <td>50440</td>
                                <td>
                                    <div class="name-cell">
                                        <div><span class="server-name">NL-UAE01</span>
                                            <p class="server-specs">2xAMD EPYC 7451/256GB/2x960Gb SSD</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./nl.svg" alt="" width="30px"></div> NL
                                    </div>
                                </td>
                                <td>84.245.19.7</td>
                                <td>
                                    <div class="price">€200.00 / monthly</div>
                                    <div class="paid-till">Paid till 5/21/2026</div>
                                </td>
                            </tr>
                            <tr>
                                <td>50552</td>
                                <td>
                                    <div class="name-cell">
                                        <div><span class="server-name">NL-TRADE13</span>
                                            <p class="server-specs">2xAMD EPYC 7451/256GB/2x960Gb SSD</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./nl.svg" alt="" width="30px"></div> NL
                                    </div>
                                </td>
                                <td>84.245.19.8</td>
                                <td>
                                    <div class="price">€200.00 / monthly</div>
                                    <div class="paid-till">Paid till 6/24/2026</div>
                                </td>
                            </tr>
                            <tr>
                                <td>51888</td>
                                <td>
                                    <div class="name-cell">
                                        <div><span class="server-name">NL-TRADE05</span>
                                            <p class="server-specs">2xAMD EPYC 7451/256GB/2x960Gb SSD</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./nl.svg" alt="" width="30px"></div> NL
                                    </div>
                                </td>
                                <td>81.22.134.5</td>
                                <td>
                                    <div class="price">€240.00 / monthly</div>
                                    <div class="paid-till">Paid till 6/1/2026</div>
                                </td>
                            </tr>
                            <tr>
                                <td>51892</td>
                                <td>
                                    <div class="name-cell">
                                        <div><span class="server-name">NL-TRADE06</span>
                                            <p class="server-specs">2xAMD EPYC 7451/256GB/2x960Gb SSD</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./nl.svg" alt="" width="30px"></div> NL
                                    </div>
                                </td>
                                <td>81.22.134.3</td>
                                <td>
                                    <div class="price">€240.00 / monthly</div>
                                    <div class="paid-till">Paid till 6/1/2026</div>
                                </td>
                            </tr>
                            <tr>
                                <td>51973</td>
                                <td>
                                    <div class="name-cell">
                                        <div><span class="server-name">NL-TRADE02</span>
                                            <p class="server-specs">2xAMD EPYC 7451/256GB/2x960Gb SSD</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./nl.svg" alt="" width="30px"></div> NL
                                    </div>
                                </td>
                                <td>81.22.134.7</td>
                                <td>
                                    <div class="price">€200.00 / monthly</div>
                                    <div class="paid-till">Paid till 6/9/2026</div>
                                </td>
                            </tr>
                            <tr>
                                <td>52497</td>
                                <td>
                                    <div class="name-cell">
                                        <div><span class="server-name">NL-TRADE03</span>
                                            <p class="server-specs">2xAMD EPYC 7451/256GB/2x960Gb SSD</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./nl.svg" alt="" width="30px"></div> NL
                                    </div>
                                </td>
                                <td>81.22.134.8</td>
                                <td>
                                    <div class="price">€200.00 / monthly</div>
                                    <div class="paid-till">Paid till 5/12/2026</div>
                                </td>
                            </tr>
                            <tr>
                                <td>52647</td>
                                <td>
                                    <div class="name-cell">
                                        <div><span class="server-name">NL-TRADE10</span>
                                            <p class="server-specs">2xAMD EPYC 7451/256GB/2x960Gb SSD</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./nl.svg" alt="" width="30px"></div> NL
                                    </div>
                                </td>
                                <td>81.22.134.12</td>
                                <td>
                                    <div class="price">€150.00 / monthly</div>
                                    <div class="paid-till">Paid till 5/21/2026</div>
                                </td>
                            </tr>
                            <tr>
                                <td>52749</td>
                                <td>
                                    <div class="name-cell">
                                        <div><span class="server-name">NL-TRADE11</span>
                                            <p class="server-specs">2xAMD EPYC 7451/256GB/2x960Gb SSD</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./nl.svg" alt="" width="30px"></div> NL
                                    </div>
                                </td>
                                <td>81.22.134.13</td>
                                <td>
                                    <div class="price">€240.00 / monthly</div>
                                    <div class="paid-till">Paid till 6/5/2026</div>
                                </td>
                            </tr>
                            <tr>
                                <td>55281</td>
                                <td>
                                    <div class="name-cell">
                                        <div><span class="server-name">Dezvarei02</span>
                                            <p class="server-specs">AMD Ryzen 9 7950X/128GB/2x1.92Tb U2 NVMe</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status active"><i class="far fa-play-circle"></i> Active</span></td>
                                <td>
                                    <div class="location d-flex align-item-center">
                                        <div class="icon"><img src="./nl.svg" alt="" width="30px"></div> NL
                                    </div>
                                </td>
                                <td>84.245.19.250</td>
                                <td>
                                    <div class="price">€169.00 / monthly</div>
                                    <div class="paid-till">Paid till 5/21/2026</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="../../../src/js/bootstrap.bundle.min.js"></script>
    <script src="./main.js"></script>
</body>

</html>