<html lang="en" class="appearance-none dark-mode cvonfc">

<head>
    <title>Edit</title>

    <link rel="stylesheet" href="{{ asset('css/Edit/Edit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/Edit/dark-14047f4f0c.css') }}">
    <link rel="stylesheet" href="{{ asset('css/Edit/style-7a9b8b33de.css') }}">
</head>

<style>
    /* Modal base styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
    }

    /* Modal inner box */
    .modal-content {
        background-color: #222;
        color: white;
        margin: 15% auto;
        padding: 20px;
        border-radius: 8px;
        width: 350px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        text-align: center;
    }

    .modal-actions {
        margin-top: 20px;
    }

    .inputButton.danger {
        background-color: #e74c3c;
        color: white;
        border: none;
        padding: 6px 14px;
        cursor: pointer;
        border-radius: 4px;
    }

    .inputButton.danger:hover {
        background-color: #c0392b;
    }    
</style>

<body class="page-common  ownlist_manga_update" data-ms="false" data-country-code="KZ" data-time="1741691968">
    <div id="myanimelist">
        <div class="wrapper">
            <div id="contentWrapper">
                <div>
                    <h1 class="h1">Edit Work</h1>
                </div>

                <div id="content">
                    <table id="dialog" cellpadding="0" cellspacing="0" style="width: 650px;">
                        <tbody>
                            <tr>
                                <td>
                                    <div class="normal_header" style="margin-top: 0; text-align: left;">
                                        Edit Work
                                    </div>
                                    <div style="text-align: left;">
                                        <form name="edit_work" method="post" id="main-form"
                                            action="/update/{{ $product->id }}">
                                            @csrf

                                            <input type="hidden" name="redirect"
                                                value="{{ request('redirect', url('/')) }}">

                                            <div id="top-submit-buttons" class="mt8 mb8" style="text-align: center;">
                                                <input type="submit" class="inputButton main_submit" value="Submit">
                                            </div>
                                            <table cellpadding="5" cellspacing="0" width="100%">
                                                <tbody>
                                                    <tr>
                                                        <td width="130" class="borderClass" valign="top">RJ Code +
                                                            Title
                                                        </td>
                                                        <td class="borderClass">
                                                            <strong>
                                                                {{ $product->id }} - {{ $product->work_name }}
                                                            </strong>
                                                        </td>                                                    
                                                    </tr>

                                                    <tr>
                                                        <td class="borderClass">Status</td>
                                                        <td class="borderClass">
                                                            <select id="progress" name="progress" class="inputtext">
                                                                <option value="Plan to Listen"
                                                                    {{ $product->progress == 'Plan to Listen' ? 'selected' : '' }}>
                                                                    Plan to Listen</option>
                                                                <option value="Listening"
                                                                    {{ $product->progress == 'Listening' ? 'selected' : '' }}>
                                                                    Listening</option>
                                                                <option value="Completed"
                                                                    {{ $product->progress == 'Completed' ? 'selected' : '' }}>
                                                                    Completed</option>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="borderClass">Your Score</td>
                                                        <td class="borderClass">
                                                            <select id="score" name="score" class="inputtext">
                                                                <option value=""
                                                                    {{ $product->score == null ? 'selected' : '' }}>
                                                                    Select score</option>
                                                                <option value="10"
                                                                    {{ $product->score == 10 ? 'selected' : '' }}>(10)
                                                                    Masterpiece</option>
                                                                <option value="9"
                                                                    {{ $product->score == 9 ? 'selected' : '' }}>(9)
                                                                    Great</option>
                                                                <option value="8"
                                                                    {{ $product->score == 8 ? 'selected' : '' }}>(8)
                                                                    Very Good</option>
                                                                <option value="7"
                                                                    {{ $product->score == 7 ? 'selected' : '' }}>(7)
                                                                    Good</option>
                                                                <option value="6"
                                                                    {{ $product->score == 6 ? 'selected' : '' }}>(6)
                                                                    Fine</option>
                                                                <option value="5"
                                                                    {{ $product->score == 5 ? 'selected' : '' }}>(5)
                                                                    Average</option>
                                                                <option value="4"
                                                                    {{ $product->score == 4 ? 'selected' : '' }}>(4)
                                                                    Bad</option>
                                                                <option value="3"
                                                                    {{ $product->score == 3 ? 'selected' : '' }}>(3)
                                                                    Very Bad</option>
                                                                <option value="2"
                                                                    {{ $product->score == 2 ? 'selected' : '' }}>(2)
                                                                    Horrible</option>
                                                                <option value="1"
                                                                    {{ $product->score == 1 ? 'selected' : '' }}>(1)
                                                                    Appalling</option>
                                                            </select>

                                                        </td>
                                                    </tr>

                                                    <tr>
                                                        <td class="borderClass" valign="top">Series</td>
                                                        <td class="borderClass">
                                                            <textarea id="series" name="series" class="inputtext" rows="2" cols="65">{{ $product->series }}</textarea>
                                                        </td>
                                                    </tr>

                                                    {{-- <tr>
                                                        <td class="borderClass">Start Date</td>
                                                        <td class="borderClass">
                                                            Month:
                                                            <select id="add_manga_start_date_month"
                                                                name="add_manga[start_date][month]" required="required"
                                                                class="inputtext">
                                                                <option value=""></option>
                                                                <option value="1">Jan</option>
                                                                <option value="2">Feb</option>
                                                                <option value="3" selected="selected">Mar
                                                                </option>
                                                                <option value="4">Apr</option>
                                                                <option value="5">May</option>
                                                                <option value="6">Jun</option>
                                                                <option value="7">Jul</option>
                                                                <option value="8">Aug</option>
                                                                <option value="9">Sep</option>
                                                                <option value="10">Oct</option>
                                                                <option value="11">Nov</option>
                                                                <option value="12">Dec</option>
                                                            </select>
                                                            Day:
                                                            <select id="add_manga_start_date_day"
                                                                name="add_manga[start_date][day]" required="required"
                                                                class="inputtext">
                                                                <option value=""></option>
                                                                <option value="1">1</option>
                                                                <option value="2">2</option>
                                                                <option value="3">3</option>
                                                                <option value="4">4</option>
                                                                <option value="5">5</option>
                                                                <option value="6">6</option>
                                                                <option value="7">7</option>
                                                                <option value="8">8</option>
                                                                <option value="9">9</option>
                                                                <option value="10">10</option>
                                                                <option value="11">11</option>
                                                                <option value="12">12</option>
                                                                <option value="13">13</option>
                                                                <option value="14">14</option>
                                                                <option value="15">15</option>
                                                                <option value="16">16</option>
                                                                <option value="17">17</option>
                                                                <option value="18">18</option>
                                                                <option value="19">19</option>
                                                                <option value="20">20</option>
                                                                <option value="21">21</option>
                                                                <option value="22" selected="selected">22</option>
                                                                <option value="23">23</option>
                                                                <option value="24">24</option>
                                                                <option value="25">25</option>
                                                                <option value="26">26</option>
                                                                <option value="27">27</option>
                                                                <option value="28">28</option>
                                                                <option value="29">29</option>
                                                                <option value="30">30</option>
                                                                <option value="31">31</option>
                                                            </select>
                                                            Year:
                                                            <select id="add_manga_start_date_year"
                                                                name="add_manga[start_date][year]" required="required"
                                                                class="inputtext">
                                                                <option value=""></option>
                                                                <option value="2025">2025</option>
                                                                <option value="2024">2024</option>
                                                                <option value="2023">2023</option>
                                                                <option value="2022">2022</option>
                                                                <option value="2021">2021</option>
                                                                <option value="2020">2020</option>
                                                                <option value="2019" selected="selected">2019
                                                                </option>
                                                                <option value="2018">2018</option>
                                                                <option value="2017">2017</option>
                                                                <option value="2016">2016</option>
                                                                <option value="2015">2015</option>
                                                                <option value="2014">2014</option>
                                                                <option value="2013">2013</option>
                                                                <option value="2012">2012</option>
                                                                <option value="2011">2011</option>
                                                                <option value="2010">2010</option>
                                                                <option value="2009">2009</option>
                                                                <option value="2008">2008</option>
                                                                <option value="2007">2007</option>
                                                                <option value="2006">2006</option>
                                                                <option value="2005">2005</option>
                                                                <option value="2004">2004</option>
                                                                <option value="2003">2003</option>
                                                                <option value="2002">2002</option>
                                                                <option value="2001">2001</option>
                                                                <option value="2000">2000</option>
                                                                <option value="1999">1999</option>
                                                                <option value="1998">1998</option>
                                                                <option value="1997">1997</option>
                                                                <option value="1996">1996</option>
                                                                <option value="1995">1995</option>
                                                            </select>
                                                            <small>
                                                                <a href="javascript:void(0);"
                                                                    id="start_date_insert_today">Insert Today</a>
                                                                <label>
                                                                    <input type="checkbox" id="unknown_start"
                                                                        value="1">
                                                                    Unknown Date
                                                                </label>
                                                            </small>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="borderClass">Finish Date</td>
                                                        <td class="borderClass">
                                                            Month:
                                                            <select id="add_manga_finish_date_month"
                                                                name="add_manga[finish_date][month]"
                                                                required="required" class="inputtext">
                                                                <option value="" selected="selected"></option>
                                                                <option value="1">Jan</option>
                                                                <option value="2">Feb</option>
                                                                <option value="3">Mar</option>
                                                                <option value="4">Apr</option>
                                                                <option value="5">May</option>
                                                                <option value="6">Jun</option>
                                                                <option value="7">Jul</option>
                                                                <option value="8">Aug</option>
                                                                <option value="9">Sep</option>
                                                                <option value="10">Oct</option>
                                                                <option value="11">Nov</option>
                                                                <option value="12">Dec</option>
                                                            </select>
                                                            Day:
                                                            <select id="add_manga_finish_date_day"
                                                                name="add_manga[finish_date][day]" required="required"
                                                                class="inputtext">
                                                                <option value="" selected="selected"></option>
                                                                <option value="1">1</option>
                                                                <option value="2">2</option>
                                                                <option value="3">3</option>
                                                                <option value="4">4</option>
                                                                <option value="5">5</option>
                                                                <option value="6">6</option>
                                                                <option value="7">7</option>
                                                                <option value="8">8</option>
                                                                <option value="9">9</option>
                                                                <option value="10">10</option>
                                                                <option value="11">11</option>
                                                                <option value="12">12</option>
                                                                <option value="13">13</option>
                                                                <option value="14">14</option>
                                                                <option value="15">15</option>
                                                                <option value="16">16</option>
                                                                <option value="17">17</option>
                                                                <option value="18">18</option>
                                                                <option value="19">19</option>
                                                                <option value="20">20</option>
                                                                <option value="21">21</option>
                                                                <option value="22">22</option>
                                                                <option value="23">23</option>
                                                                <option value="24">24</option>
                                                                <option value="25">25</option>
                                                                <option value="26">26</option>
                                                                <option value="27">27</option>
                                                                <option value="28">28</option>
                                                                <option value="29">29</option>
                                                                <option value="30">30</option>
                                                                <option value="31">31</option>
                                                            </select>
                                                            Year:
                                                            <select id="add_manga_finish_date_year"
                                                                name="add_manga[finish_date][year]"
                                                                required="required" class="inputtext">
                                                                <option value="" selected="selected"></option>
                                                                <option value="2025">2025</option>
                                                                <option value="2024">2024</option>
                                                                <option value="2023">2023</option>
                                                                <option value="2022">2022</option>
                                                                <option value="2021">2021</option>
                                                                <option value="2020">2020</option>
                                                                <option value="2019">2019</option>
                                                                <option value="2018">2018</option>
                                                                <option value="2017">2017</option>
                                                                <option value="2016">2016</option>
                                                                <option value="2015">2015</option>
                                                                <option value="2014">2014</option>
                                                                <option value="2013">2013</option>
                                                                <option value="2012">2012</option>
                                                                <option value="2011">2011</option>
                                                                <option value="2010">2010</option>
                                                                <option value="2009">2009</option>
                                                                <option value="2008">2008</option>
                                                                <option value="2007">2007</option>
                                                                <option value="2006">2006</option>
                                                                <option value="2005">2005</option>
                                                                <option value="2004">2004</option>
                                                                <option value="2003">2003</option>
                                                                <option value="2002">2002</option>
                                                                <option value="2001">2001</option>
                                                                <option value="2000">2000</option>
                                                                <option value="1999">1999</option>
                                                                <option value="1998">1998</option>
                                                                <option value="1997">1997</option>
                                                                <option value="1996">1996</option>
                                                                <option value="1995">1995</option>
                                                            </select>
                                                            <small>
                                                                <a href="javascript:void(0);"
                                                                    id="end_date_insert_today">Insert Today</a>
                                                                <label>
                                                                    <input type="checkbox" id="unknown_end"
                                                                        value="1">
                                                                    Unknown Date
                                                                </label>
                                                            </small>
                                                        </td>
                                                    </tr> --}}

                                                     <tr>
                                                        <td class="borderClass" valign="top">Title Japanese</td>
                                                        <td class="borderClass">
                                                            <textarea id="work_name" name="work_name" class="inputtext" rows="3" cols="65" required>{{ $product->work_name }}</textarea>
                                                            @if ($errors->has('work_name'))
                                                                <div class="text-error">{{ $errors->first('work_name') }}</div>
                                                            @endif
                                                        </td>                                                        
                                                    </tr>

                                                    <tr>
                                                        <td class="borderClass" valign="top">Title English</td>
                                                        <td class="borderClass">
                                                            <textarea id="work_name_english" name="work_name_english" class="inputtext" rows="3" cols="65">{{ $product->work_name_english }}</textarea>
                                                        </td>
                                                    </tr>                                                

                                                    <tr>
                                                        <td width="130" class="borderClass">Custom Tags</td>
                                                        <td class="borderClass">
                                                            <textarea id="genre_custom" name="genre_custom" class="textarea" rows="5" cols="65">{{ implode(', ', json_decode($product->genre_custom, true)) }}</textarea>
                                                        </td>
                                                    </tr>

                                                    <tr>
                                                        <td class="borderClass" valign="top">Notes</td>
                                                        <td class="borderClass">
                                                            <textarea id="add_notes" name="notes" class="inputtext" rows="5" cols="65">{{ $product->notes }}</textarea>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <div class="mt8 mb8" style="text-align: center;">
                                                <input type="submit" class="inputButton main_submit" value="Submit">

                                            </div>
                                        </form>

                                        <form style="text-align: right;" id="delete-form" method="POST"
                                            action="/destroy/{{ $product->id }}">
                                            @csrf

                                            <input type="hidden" name="redirect"
                                                value="{{ request('redirect', url('/')) }}">

                                            <input type="submit" class="inputButton ml8 delete_submit"
                                                value="Delete" onclick="return openDeleteModal(event);">
                                        </form>

                                        <br>

                                        <div style="text-align: right;">
                                            <a href="{{ $redirect }}#{{ $product->id }}"
                                                class="inputButton ml8 ignore-visited-link">
                                                Go back
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <p>Are you sure you want to delete this item?</p>
            <div class="modal-actions">
                <button class="inputButton danger" onclick="confirmDeletion()">Yes, Delete</button>
                <button class="inputButton ml8" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>
</body>

<script src="{{ asset('scripts/deleteConfirmation.js') }}"></script>

</html>
