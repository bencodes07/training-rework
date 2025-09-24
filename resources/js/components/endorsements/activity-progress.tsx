import { Progress } from "../ui/progress";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "../ui/tooltip";

export default function ActivityProgress({ current, status }: { current: number; status: string }) {
  const percentage = Math.min((current / 3) * 100, 100);

  let progressColor = 'bg-green-500';
  if (status === 'warning') progressColor = 'bg-yellow-500';
  if (status === 'removal') progressColor = 'bg-red-500';

  return (
      <TooltipProvider>
          <Tooltip>
              <TooltipTrigger asChild>
                  <div className="max-w-40">
                      <div className="mb-1 flex justify-between text-xs">
                          <span>{current}h</span>
                          <span>of</span>
                          <span>3h</span>
                      </div>
                      <Progress value={percentage} className={`h-2`} colorClass={progressColor} />
                  </div>
              </TooltipTrigger>
              <TooltipContent>
                  <p>
                      {current} of 3 hours in the last 180 days ({percentage.toFixed(1)}%)
                  </p>
              </TooltipContent>
          </Tooltip>
      </TooltipProvider>
  );
}