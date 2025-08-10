import React, { useState } from 'react';
import { format, addMonths, subMonths, startOfMonth, endOfMonth, eachDayOfInterval, isSameMonth, isSameDay, startOfWeek, endOfWeek } from 'date-fns';
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/Components/ui/ui/button';

const DatePicker = ({ 
  value, 
  onChange, 
  placeholder = "Pick a date", 
  className = "",
  disabled = false,
  error = null
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [currentMonth, setCurrentMonth] = useState(value ? new Date(value) : new Date());

  const handleDateSelect = (date) => {
    onChange(date);
    setIsOpen(false);
  };

  const formatDisplayValue = () => {
    if (!value) return '';
    const date = new Date(value);
    return format(date, 'MMM dd, yyyy');
  };

  const goToPreviousMonth = () => {
    setCurrentMonth(subMonths(currentMonth, 1));
  };

  const goToNextMonth = () => {
    setCurrentMonth(addMonths(currentMonth, 1));
  };

  const goToToday = () => {
    setCurrentMonth(new Date());
  };

  const generateCalendarDays = () => {
    const monthStart = startOfMonth(currentMonth);
    const monthEnd = endOfMonth(currentMonth);
    const calendarStart = startOfWeek(monthStart);
    const calendarEnd = endOfWeek(monthEnd);

    return eachDayOfInterval({ start: calendarStart, end: calendarEnd });
  };

  const isToday = (date) => {
    return isSameDay(date, new Date());
  };

  const isSelected = (date) => {
    return value && isSameDay(date, new Date(value));
  };

  const isCurrentMonth = (date) => {
    return isSameMonth(date, currentMonth);
  };

  return (
    <div className={cn("relative", className)}>
      <Button
        variant="outline"
        className={cn(
          "w-full justify-start text-left font-normal",
          !value && "text-muted-foreground",
          error && "border-red-500 focus:ring-red-500",
          disabled && "opacity-50 cursor-not-allowed"
        )}
        disabled={disabled}
        onClick={() => !disabled && setIsOpen(!isOpen)}
      >
        <CalendarIcon className="mr-2 h-4 w-4" />
        {formatDisplayValue() || placeholder}
      </Button>

      {error && (
        <p className="mt-1 text-sm text-red-500">{error}</p>
      )}

      {isOpen && !disabled && (
        <div className="absolute top-full left-0 z-50 mt-1 w-80 rounded-md border bg-popover p-4 text-popover-foreground shadow-md">
          <div className="space-y-4">
            {/* Header with month/year and navigation */}
            <div className="flex items-center justify-between">
              <Button
                variant="outline"
                size="sm"
                onClick={goToPreviousMonth}
                className="h-8 w-8 p-0"
              >
                <ChevronLeft className="h-4 w-4" />
              </Button>
              <div className="text-center">
                <div className="text-sm font-medium">
                  {format(currentMonth, 'MMMM yyyy')}
                </div>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={goToNextMonth}
                className="h-8 w-8 p-0"
              >
                <ChevronRight className="h-4 w-4" />
              </Button>
            </div>

            {/* Today button */}
            <div>
              <Button
                variant="outline"
                size="sm"
                onClick={goToToday}
                className="w-full text-xs"
              >
                Today
              </Button>
            </div>

            {/* Calendar grid */}
            <div className="grid grid-cols-7 gap-1">
              {/* Day headers */}
              {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => (
                <div key={day} className="text-center text-xs font-medium text-muted-foreground p-1">
                  {day}
                </div>
              ))}

              {/* Calendar days */}
              {generateCalendarDays().map((day, index) => (
                <Button
                  key={index}
                  variant="ghost"
                  size="sm"
                  className={cn(
                    "h-8 w-8 p-0 text-xs",
                    !isCurrentMonth(day) && "text-muted-foreground opacity-50",
                    isToday(day) && "bg-accent text-accent-foreground",
                    isSelected(day) && "bg-primary text-primary-foreground hover:bg-primary",
                    !isSelected(day) && !isToday(day) && "hover:bg-accent hover:text-accent-foreground"
                  )}
                  onClick={() => handleDateSelect(day)}
                >
                  {format(day, 'd')}
                </Button>
              ))}
            </div>

            {/* Close button */}
            <div className="flex justify-end pt-2 border-t">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setIsOpen(false)}
                className="text-xs"
              >
                Close
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default DatePicker; 